<?php

namespace Tests\Feature;

use App\Models\{Company, MineOperationalRecord, TindakLanjut, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tindak lanjut yang dipakai bersama seluruh modul.
 *
 * Yang diuji terutama dua hal yang sebelumnya gagal diam-diam:
 * keterlambatan yang tidak pernah bertambah sendiri karena disimpan
 * sebagai status, dan peringatan yang tidak pernah tahu apakah sudah
 * ada yang menanganinya.
 */
class TindakLanjutTest extends TestCase
{
    use RefreshDatabase;

    private function buat(array $ganti = []): TindakLanjut
    {
        return TindakLanjut::create(array_merge([
            'modul' => 'operasi', 'judul' => 'Turunkan delay pemuatan',
            'prioritas' => 'tinggi', 'status' => 'berjalan',
        ], $ganti));
    }

    /* ---------- keterlambatan terhitung ---------- */

    public function test_keterlambatan_dihitung_dari_tanggal_bukan_disimpan(): void
    {
        $t = $this->buat(['target_selesai' => now()->subDays(30)->toDateString()]);

        $this->assertTrue($t->terlambat(),
            'Yang targetnya lewat 30 hari harus terbaca terlambat tanpa ada yang menyuntingnya.');
        $this->assertSame(30, $t->hariTerlambat());
        $this->assertStringContainsString('terlambat 30 hari', $t->label());
    }

    public function test_yang_sudah_selesai_tidak_pernah_terlambat(): void
    {
        $t = $this->buat(['status' => 'selesai', 'target_selesai' => now()->subDays(30)->toDateString()]);

        $this->assertFalse($t->terlambat());
        $this->assertSame(0, $t->hariTerlambat());
    }

    public function test_tanpa_target_selesai_tidak_pernah_terlambat(): void
    {
        $this->assertFalse($this->buat(['target_selesai' => null])->terlambat());
    }

    public function test_saringan_terlambat_sejalan_dengan_hitungannya(): void
    {
        $this->buat(['target_selesai' => now()->subDay()->toDateString()]);
        $this->buat(['target_selesai' => now()->addDay()->toDateString()]);
        $this->buat(['status' => 'selesai', 'target_selesai' => now()->subDay()->toDateString()]);

        $this->assertSame(1, TindakLanjut::terlambatSaja()->count());
    }

    public function test_urutan_menaruh_yang_terlambat_lebih_dulu(): void
    {
        $this->buat(['judul' => 'Belum jatuh tempo', 'prioritas' => 'kritis', 'target_selesai' => now()->addWeek()->toDateString()]);
        $this->buat(['judul' => 'Sudah terlambat', 'prioritas' => 'rendah', 'target_selesai' => now()->subWeek()->toDateString()]);

        $this->assertSame('Sudah terlambat', TindakLanjut::urutMendesak()->first()->judul);
    }

    /* ---------- lingkaran peringatan → tindak lanjut ---------- */

    public function test_peringatan_yang_sudah_ditangani_ditandai_pada_halaman(): void
    {
        $company = Company::create(['name' => 'Tambang Uji']);
        $u = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($u)->post(route('operasi.tindak.simpan'), [
            'kode_pemicu' => 'delay-tinggi', 'judul' => 'Uraikan penyebab delay',
            'prioritas' => 'tinggi',
        ])->assertSessionHasNoErrors();

        $props = $this->actingAs($u)->get(route('operasi.index'))->assertOk()->viewData('page')['props'];

        $this->assertContains('delay-tinggi', (array) $props['kodeDitangani'],
            'Peringatan yang sudah punya tindak lanjut terbuka harus dapat ditandai.');
    }

    public function test_yang_sudah_ditutup_tidak_lagi_menandai_peringatannya(): void
    {
        $u = User::factory()->create();
        $t = $this->buat(['kode_pemicu' => 'delay-tinggi', 'status' => 'selesai']);

        $props = $this->actingAs($u)->get(route('operasi.index'))->assertOk()->viewData('page')['props'];

        $this->assertNotContains('delay-tinggi', (array) $props['kodeDitangani'],
            'Yang sudah ditutup berarti peringatannya kembali menuntut perhatian.');
    }

    /* ---------- alur pemakaian ---------- */

    public function test_tindak_lanjut_dapat_menunjuk_catatan_asalnya(): void
    {
        $company = Company::create(['name' => 'Tambang Uji']);
        $u = User::factory()->create(['company_id' => $company->id]);

        $rec = MineOperationalRecord::create([
            'company_id' => $company->id, 'tanggal' => now()->toDateString(), 'shift' => 'siang',
            'material' => 'Batubara', 'produksi_ton' => 100, 'overburden_bcm' => 200,
            'jarak_angkut_km' => 2, 'jam_operasi' => 8, 'jam_delay' => 5,
        ]);

        $this->actingAs($u)->post(route('operasi.tindak.simpan'), [
            'record_id' => $rec->id, 'judul' => 'Periksa unit DT-005', 'prioritas' => 'kritis',
        ])->assertSessionHasNoErrors();

        $t = TindakLanjut::firstOrFail();
        $this->assertTrue($t->sumber->is($rec));
    }

    public function test_menutup_tindak_lanjut_mencatat_tanggalnya(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $t = $this->buat();

        $this->actingAs($admin)
            ->put(route('operasi.tindak.ubah', $t), ['status' => 'selesai'])
            ->assertSessionHasNoErrors();

        $t->refresh();
        $this->assertSame('selesai', $t->status);
        $this->assertSame(now()->toDateString(), $t->selesai_pada?->toDateString(),
            'Tanggal penutupan dicatat saat status berpindah, bukan diketik terpisah.');
    }

    public function test_membuka_kembali_menghapus_tanggal_selesainya(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $t = $this->buat(['status' => 'selesai', 'selesai_pada' => now()->toDateString()]);

        $this->actingAs($admin)->put(route('operasi.tindak.ubah', $t), ['status' => 'berjalan']);

        $this->assertNull($t->fresh()->selesai_pada);
    }

    /* ---------- batas per perusahaan ---------- */

    public function test_tidak_terlihat_oleh_perusahaan_lain(): void
    {
        $a = Company::create(['name' => 'Tambang A']);
        $b = Company::create(['name' => 'Tambang B']);

        $this->actingAs(User::factory()->create(['company_id' => $a->id]))
            ->post(route('operasi.tindak.simpan'), ['judul' => 'Milik A', 'prioritas' => 'sedang']);

        $props = $this->actingAs(User::factory()->create(['company_id' => $b->id]))
            ->get(route('operasi.index'))->assertOk()->viewData('page')['props'];

        $this->assertCount(0, $props['tindak'],
            'Tindak lanjut perusahaan lain tidak boleh terlihat.');
    }
}
