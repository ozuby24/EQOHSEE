<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Miners\{Jabatan, Kendaraan, McuOrang, Mcu, Pekerja};
use App\Models\User;
use App\Support\Miners\MasterMiners;
use App\Support\Waktu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Master Data, Perusahaan, dan Manpower Miners — layar Safe Track.
 *
 * Yang dijaga:
 *   · master data hanya dikelola admin atau HSE IUP ("admin" Safe Track);
 *     daftar BERSAMA hanya diubah administrator;
 *   · baris yang masih dipakai dinonaktifkan, tidak dihapus;
 *   · manpower dengan riwayat HR TIDAK PERNAH dihapus keras — HRIS
 *     menunjuk mnr_pekerja dengan cascadeOnDelete;
 *   · NIK unik per perusahaan; status aktif/tidak aktif hanya oleh admin.
 */
class MinersMasterDataTest extends TestCase
{
    use RefreshDatabase;

    private Company $iup;
    private Company $mitra;
    private User $admin;
    private User $ohseIup;
    private User $mitraUser;

    protected function setUp(): void
    {
        parent::setUp();

        MasterMiners::pasang();

        $this->iup   = Company::create(['name' => 'PT IUP Master', 'code' => 'IM']);
        $this->mitra = Company::create(['name' => 'PT Mitra Master', 'code' => 'MM', 'izin_type' => 'IUJP', 'parent_id' => $this->iup->id]);

        $this->admin     = User::factory()->create(['is_admin' => true]);
        $this->ohseIup   = User::factory()->create(['company_id' => $this->iup->id, 'ohse_role' => 'ohse']);
        $this->mitraUser = User::factory()->create(['company_id' => $this->mitra->id]);
    }

    /* ═══════════ master data ═══════════ */

    public function test_setiap_daftar_master_terbuka_bagi_admin(): void
    {
        $this->actingAs($this->admin);

        foreach (['unit', 'type-unit', 'sub-unit', 'jabatan', 'departemen', 'hasil-mcu', 'pjo', 'subkontraktor'] as $j) {
            $this->get("/miners/master/{$j}")->assertOk()
                ->assertInertia(fn (AssertableInertia $h) => $h->component('Miners/Master')->has('medan')->has('baris'));
        }

        $this->get('/miners/master/perusahaan')->assertOk()
            ->assertInertia(fn (AssertableInertia $h) => $h->component('Miners/Perusahaan'));
    }

    public function test_mitra_tidak_mengelola_master_data(): void
    {
        $this->actingAs($this->mitraUser)->get('/miners/master/unit')->assertForbidden();
        $this->actingAs($this->mitraUser)->post('/miners/master/jabatan', ['nama' => 'Coba'])->assertForbidden();
        $this->actingAs($this->ohseIup)->get('/miners/master/perusahaan')->assertForbidden();
    }

    public function test_hse_iup_menambah_ke_daftar_perusahaannya_sendiri(): void
    {
        $this->actingAs($this->ohseIup)
            ->post('/miners/master/unit', ['nama' => 'Articulated Dump Truck', 'kode' => 'ADT', 'kelas_simpol' => 'B2 Umum', 'wajib_sio' => '1'])
            ->assertSessionHasNoErrors();

        $u = Kendaraan::withoutGlobalScopes()->where('nama', 'Articulated Dump Truck')->firstOrFail();

        $this->assertSame($this->iup->id, (int) $u->company_id);
        $this->assertTrue($u->wajib_sio);
        $this->assertSame('B2 Umum', $u->kelas_simpol);
    }

    public function test_daftar_bersama_hanya_diubah_admin(): void
    {
        $bersama = Kendaraan::withoutGlobalScopes()->whereNull('company_id')->firstOrFail();

        $this->actingAs($this->ohseIup)
            ->put("/miners/master/unit/{$bersama->id}", ['nama' => 'Diubah'])
            ->assertForbidden();

        $this->actingAs($this->admin)
            ->put("/miners/master/unit/{$bersama->id}", ['nama' => $bersama->nama, 'kode' => 'ZZ'])
            ->assertSessionHasNoErrors();

        $this->assertSame('ZZ', $bersama->fresh()->kode);
    }

    public function test_yang_masih_dipakai_dinonaktifkan_bukan_dihapus(): void
    {
        $this->actingAs($this->admin);

        $jab = Jabatan::withoutGlobalScopes()->create(['nama' => 'Jabatan Uji']);
        Pekerja::withoutGlobalScopes()->create(['company_id' => $this->iup->id, 'nama' => 'A', 'jabatan_id' => $jab->id]);

        $this->delete("/miners/master/jabatan/{$jab->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseHas('mnr_jabatan', ['id' => $jab->id, 'aktif' => false]);

        $kosong = Jabatan::withoutGlobalScopes()->create(['nama' => 'Tidak Dipakai']);
        $this->delete("/miners/master/jabatan/{$kosong->id}");
        $this->assertDatabaseMissing('mnr_jabatan', ['id' => $kosong->id]);
    }

    public function test_pemetaan_mitra_ke_iup(): void
    {
        $lepas = Company::create(['name' => 'PT Jasa Lepas', 'izin_type' => 'IUJP']);

        $this->actingAs($this->admin)
            ->put("/miners/master/perusahaan/{$lepas->id}", ['parent_id' => $this->iup->id, 'nama_pendek' => 'PT. JL'])
            ->assertSessionHasNoErrors();

        $this->assertSame($this->iup->id, (int) $lepas->fresh()->parent_id);
        $this->assertSame('PT. JL', $lepas->fresh()->nama_pendek);

        /* Induk harus IUP — mitra tidak dapat menjadi induk mitra lain. */
        $this->actingAs($this->admin)
            ->put("/miners/master/perusahaan/{$lepas->id}", ['parent_id' => $this->mitra->id])
            ->assertSessionHasErrors('parent_id');
    }

    /* ═══════════ manpower ═══════════ */

    public function test_manpower_menyajikan_zona_rekap_dan_halaman(): void
    {
        $p = Pekerja::withoutGlobalScopes()->create(['company_id' => $this->iup->id, 'nama' => 'Berlaku Lama', 'status' => 'aktif']);
        $q = Pekerja::withoutGlobalScopes()->create(['company_id' => $this->iup->id, 'nama' => 'Hampir Habis', 'status' => 'aktif']);
        Pekerja::withoutGlobalScopes()->create(['company_id' => $this->iup->id, 'nama' => 'Belum MCU', 'status' => 'nonaktif']);

        $surat = Mcu::withoutGlobalScopes()->create(['company_id' => $this->iup->id, 'tanggal' => Waktu::kini()->startOfDay(), 'status' => 'selesai']);
        McuOrang::create(['mcu_id' => $surat->id, 'pekerja_id' => $p->id, 'nama' => $p->nama, 'berlaku_sampai' => Waktu::kini()->addDays(200)->startOfDay()]);
        McuOrang::create(['mcu_id' => $surat->id, 'pekerja_id' => $q->id, 'nama' => $q->nama, 'berlaku_sampai' => Waktu::kini()->addDays(10)->startOfDay()]);

        $this->actingAs($this->admin)->get('/miners')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $h) => $h
                ->component('Miners/Daftar')
                ->where('manpower.total', 3)
                ->where('manpower.nonaktif', 1)
                ->where('ringkas.aman', 1)
                ->where('ringkas.kritis', 1)
                ->where('ringkas.kosong', 1)
                ->where('rekap.0.total', 3)
                ->has('baris.data', 3));

        $this->actingAs($this->admin)->get('/miners?zona=kritis')
            ->assertInertia(fn (AssertableInertia $h) => $h
                ->has('baris.data', 1)
                ->where('baris.data.0.nama', 'Hampir Habis')
                /* Kartu zona tidak ikut menyempit saat satu zona dipilih. */
                ->where('ringkas.total', 3));
    }

    public function test_manpower_ditambah_dengan_nik_unik_per_perusahaan(): void
    {
        $this->actingAs($this->mitraUser)
            ->post('/miners', ['nama' => 'Manpower Baru', 'nik' => '6400000000000001'])
            ->assertSessionHasNoErrors();

        $p = Pekerja::withoutGlobalScopes()->where('nama', 'Manpower Baru')->firstOrFail();
        $this->assertSame($this->mitra->id, (int) $p->company_id);
        $this->assertSame('aktif', $p->status);

        $this->actingAs($this->mitraUser)
            ->post('/miners', ['nama' => 'Kembar', 'nik' => '6400000000000001'])
            ->assertSessionHasErrors('nik');

        /* Perusahaan di luar jangkauannya ditolak. */
        $this->actingAs($this->mitraUser)
            ->post('/miners', ['nama' => 'Lompat', 'company_id' => $this->iup->id])
            ->assertSessionHasErrors('company_id');
    }

    public function test_status_karyawan_hanya_diubah_admin(): void
    {
        $p = Pekerja::withoutGlobalScopes()->create(['company_id' => $this->mitra->id, 'nama' => 'B', 'status' => 'aktif']);

        $this->actingAs($this->mitraUser)->post("/miners/{$p->id}/status", ['aktif' => 0])->assertForbidden();

        $this->actingAs($this->ohseIup)
            ->post("/miners/{$p->id}/status", ['aktif' => 0, 'tanggal' => '2026-10-01', 'alasan' => 'resign'])
            ->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('nonaktif', $p->status);
        $this->assertSame('2026-10-01', $p->tanggal_nonaktif->toDateString());
        $this->assertSame('resign', $p->alasan_nonaktif);

        $this->actingAs($this->ohseIup)->post("/miners/{$p->id}/status", ['aktif' => 1]);
        $this->assertSame('aktif', $p->fresh()->status);
        $this->assertNull($p->fresh()->tanggal_nonaktif);
    }

    public function test_manpower_dengan_riwayat_hr_dinonaktifkan_bukan_dihapus(): void
    {
        $p = Pekerja::withoutGlobalScopes()->create(['company_id' => $this->iup->id, 'nama' => 'Punya Kontrak', 'status' => 'aktif']);

        /* Satu baris riwayat HR — kontrak PKWT. Menghapus pekerjanya akan
           ikut menghapus baris ini lewat cascadeOnDelete. */
        DB::table('hr_kontrak')->insert(array_filter([
            'pekerja_id' => $p->id,
            'company_id' => \Illuminate\Support\Facades\Schema::hasColumn('hr_kontrak', 'company_id') ? $this->iup->id : null,
            'created_at' => now(), 'updated_at' => now(),
        ] + $this->kolomWajib('hr_kontrak'), fn ($v) => $v !== null));

        $this->actingAs($this->admin)->delete("/miners/{$p->id}")->assertSessionHasNoErrors();

        $this->assertDatabaseHas('mnr_pekerja', ['id' => $p->id, 'status' => 'nonaktif']);
        $this->assertSame(1, DB::table('hr_kontrak')->where('pekerja_id', $p->id)->count(), 'Riwayat HR ikut terhapus.');

        $tanpa = Pekerja::withoutGlobalScopes()->create(['company_id' => $this->iup->id, 'nama' => 'Salah Ketik', 'status' => 'aktif']);
        $this->actingAs($this->admin)->delete("/miners/{$tanpa->id}");
        $this->assertDatabaseMissing('mnr_pekerja', ['id' => $tanpa->id]);
    }

    /**
     * Nilai pengisi untuk kolom NOT NULL tanpa bawaan pada sebuah tabel.
     *
     * @return array<string,mixed>
     */
    private function kolomWajib(string $tabel): array
    {
        $isi = [];

        foreach (\Illuminate\Support\Facades\Schema::getColumns($tabel) as $k) {
            if ($k['nullable'] || $k['default'] !== null || $k['auto_increment']
                || in_array($k['name'], ['id', 'pekerja_id', 'company_id', 'created_at', 'updated_at'], true)) {
                continue;
            }

            $tipe = strtolower($k['type_name']);
            $isi[$k['name']] = match (true) {
                str_contains($tipe, 'date') => '2026-01-01',
                str_contains($tipe, 'int'), str_contains($tipe, 'dec'), str_contains($tipe, 'num') => 0,
                default => 'uji',
            };
        }

        return $isi;
    }
}
