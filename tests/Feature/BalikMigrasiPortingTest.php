<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

/**
 * Sepuluh migrasi porting SMKP (Aurorasys) dan Miners (Safe Track) dapat
 * dibalik lalu dijalankan ulang — jalan pulang bila deploy harus mundur.
 *
 * Yang dijaga: urutan pembuangan di down(). MySQL memakai indeks gabungan
 * sebagai indeks kunci asing, sehingga indeks itu tidak dapat dibuang
 * selama kuncinya masih ada; SQLite dan PostgreSQL tidak keberatan. Uji ini
 * karena itu baru menggigit bila suite dijalankan pada MySQL.
 */
class BalikMigrasiPortingTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRASI = [
        '2026_11_07_000001_kategori_perusahaan', '2026_11_07_000002_skema_smkp_aurorasys', '2026_11_07_000003_pindah_data_smkp_aurorasys',
        '2026_11_07_100001_miners_safetrack_dasar', '2026_11_08_000001_kop_formulir_perusahaan', '2026_11_08_100001_miners_safetrack_mcu_induksi_permit',
        '2026_11_11_100001_miners_safetrack_simper', '2026_11_12_100001_miners_pengingat_masa_berlaku', '2026_11_12_100002_miners_asal_data_lama',
        '2026_11_12_100003_miners_pekerja_dibuat_oleh',
    ];

    public function test_migrasi_porting_dapat_dibalik_lalu_dijalankan_ulang(): void
    {
        $terakhir = DB::table('migrations')->orderByDesc('id')->limit(count(self::MIGRASI))->pluck('migration')->sort()->values()->all();
        $this->assertSame(self::MIGRASI, $terakhir, 'Sepuluh migrasi terakhir bukan lagi migrasi porting — sesuaikan uji ini.');

        $this->artisan('migrate:rollback', ['--step' => count(self::MIGRASI), '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('mnr_skema'));
        $this->assertFalse(Schema::hasColumn('mnr_alur', 'ditugaskan_user_id'));
        $this->assertTrue(Schema::hasTable('smkp_ofi'));

        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('mnr_alur', 'ditugaskan_user_id'));
        $this->assertTrue(Schema::hasColumn('mnr_pekerja', 'dibuat_oleh'));
        $this->assertFalse(Schema::hasTable('smkp_ofi'));
    }
}
