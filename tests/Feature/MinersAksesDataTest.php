<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Miners\Pekerja;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Batas baca dan ubah data pribadi dan medis modul Miners.
 *
 * Lingkup perusahaan sudah dijaga global scope; uji ini menjaga batas DI
 * DALAM satu perusahaan: pengguna biasa tidak membaca atau mengubah data
 * orang lain hanya karena perusahaannya sama.
 */
class MinersAksesDataTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    /** Pengguna biasa di mitra: bukan pengaju, bukan penyetuju. */
    private function biasa(): User
    {
        return User::factory()->create(['company_id' => $this->mitra->id]);
    }

    /** Pengguna perusahaan lain sama sekali. */
    private function asing(): User
    {
        return User::factory()->create(['company_id' => Company::create(['name' => 'PT Asing '.Str::random(4)])->id]);
    }

    private function pekerja(?User $pemilik = null): Pekerja
    {
        return Pekerja::withoutGlobalScopes()->create([
            'company_id' => $this->mitra->id, 'nama' => 'Pekerja '.Str::random(4),
            'nik' => (string) random_int(1000000000000000, 9999999999999999), 'status' => 'aktif',
            'user_id' => $pemilik?->id,
        ]);
    }

    /* ═══════════ 1 · ubah manpower ═══════════ */

    #[Test]
    public function manpower_hanya_diubah_pengelola_atau_pendaftarnya(): void
    {
        $pemilik = $this->biasa();
        $pk = $this->pekerja($pemilik);

        $this->actingAs($this->biasa())->put('/miners/'.$pk->id, ['nama' => 'Diubah Orang Lain'])->assertForbidden();
        $this->actingAs($this->pengaju)->put('/miners/'.$pk->id, ['nama' => 'Diubah Pengaju'])->assertForbidden();
        $this->actingAs($this->asing())->put('/miners/'.$pk->id, ['nama' => 'Diubah Asing'])->assertNotFound();
        $this->assertSame($pk->nama, $pk->fresh()->nama);

        $this->actingAs($pemilik)->put('/miners/'.$pk->id, ['nama' => 'Oleh Pendaftar'])->assertSessionHasNoErrors();
        $this->assertSame('Oleh Pendaftar', $pk->fresh()->nama);

        $this->actingAs($this->ohse)->put('/miners/'.$pk->id, ['nama' => 'Oleh HSE'])->assertSessionHasNoErrors();
        $this->assertSame('Oleh HSE', $pk->fresh()->nama);
    }

    /* ═══════════ 2 · daftar rujukan ═══════════ */

    #[Test]
    public function daftar_rujukan_hanya_bagi_admin_ohse_dan_paramedis(): void
    {
        $o = $this->mcuSelesai($this->mitra, 'Rujukan');

        foreach ([$this->biasa(), $this->pengaju, $this->ktt] as $u) {
            $this->actingAs($u)->get('/miners/daftar/rujukan')->assertForbidden();
        }

        foreach ([$this->admin, $this->ohse, $this->dokter] as $u) {
            $this->actingAs($u)->get('/miners/daftar/rujukan')->assertOk()
                ->assertInertia(fn (Assert $p) => $p->where('baris.0.id', $o->id));
        }

        /* Menunya ikut hilang bagi yang pasti ditolak. */
        $this->assertFalse($this->diMenu($this->pengaju, 'miners/daftar/rujukan'));
        $this->assertTrue($this->diMenu($this->dokter, 'miners/daftar/rujukan'));
    }

    /** Apakah bilah samping Miners pengguna ini memuat alamat itu. */
    private function diMenu(User $u, string $alamat): bool
    {
        $grup = $this->actingAs($u)->get('/miners/dasbor')->viewData('page')['props']['menu']['grup'];

        return collect($grup)->flatMap(fn ($g) => $g['butir'])->contains(fn ($b) => str_ends_with($b['url'], $alamat));
    }
}
