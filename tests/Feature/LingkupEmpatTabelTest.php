<?php

namespace Tests\Feature;

use App\Models\{Company, EnergyFuelRecon, EnergyOtherLog, EnergyProduction,
                TpkkpAssessment, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Empat tabel yang dahulu tidak menyebut perusahaan sama sekali.
 *
 *   energy_production   — produksi harian, pembagi intensitas energi
 *   energy_fuel_recon   — rekonsiliasi tangki
 *   energy_other_logs   — pemakaian energi lain
 *   tpkkp_assessments   — penilaian kinerja keselamatan
 *
 * Keempatnya bocor dua arah sekaligus. Datanya terbaca perusahaan lain —
 * dan pada energy_production itu berarti produksi orang lain menjadi
 * PEMBAGI intensitas energi sendiri, sehingga angkanya salah tanpa
 * satu pun tanda di layar. Sebaliknya, `tanggal` dan `tahun` yang unik
 * se-pemasangan membuat perusahaan kedua tidak dapat mencatat sama
 * sekali: begitu satu perusahaan mencatat 16 Agustus, hari itu habis
 * untuk semua.
 *
 * Kegagalan macam ini hanya muncul pada perusahaan KEDUA, jadi ia tidak
 * pernah terlihat selama pemasangan masih dipakai satu klien.
 */
class LingkupEmpatTabelTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Company,1:Company} */
    private function dua(): array
    {
        return [
            Company::create(['name' => 'PT Alfa', 'code' => 'ALF']),
            Company::create(['name' => 'PT Beta', 'code' => 'BET']),
        ];
    }

    private function masuk(Company $c): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $c->id]));
    }

    /* ═══════════ terbaca silang ═══════════ */

    public function test_produksi_perusahaan_lain_tidak_terbaca(): void
    {
        [$a, $b] = $this->dua();

        EnergyProduction::withoutGlobalScopes()->create([
            'company_id' => $a->id, 'tanggal' => '2026-08-16', 'ton' => 8_000, 'bcm' => 6_000,
        ]);
        EnergyProduction::withoutGlobalScopes()->create([
            'company_id' => $b->id, 'tanggal' => '2026-08-16', 'ton' => 1_000, 'bcm' => 700,
        ]);

        $this->masuk($a);

        $this->assertSame([8_000.0], EnergyProduction::pluck('ton')->all(),
            'Produksi perusahaan lain ikut terbaca — dan ia menjadi pembagi intensitas energi.');
    }

    public function test_rekonsiliasi_dan_energi_lain_tidak_terbaca_silang(): void
    {
        [$a, $b] = $this->dua();

        foreach ([[$a, 100.0], [$b, 900.0]] as [$c, $liter]) {
            EnergyFuelRecon::withoutGlobalScopes()->create([
                'company_id' => $c->id, 'tanggal' => '2026-08-16',
                'disalurkan_liter' => $liter,
            ]);
            EnergyOtherLog::withoutGlobalScopes()->create([
                'company_id' => $c->id, 'tanggal' => '2026-08-16',
                'jenis' => 'LPG', 'satuan' => 'kg', 'jumlah' => $liter,
            ]);
        }

        $this->masuk($a);

        $this->assertSame([100.0], EnergyFuelRecon::pluck('disalurkan_liter')->all());
        $this->assertSame([100.0], EnergyOtherLog::pluck('jumlah')->all());
    }

    public function test_penilaian_tpkkp_perusahaan_lain_tidak_terbaca(): void
    {
        [$a, $b] = $this->dua();

        TpkkpAssessment::withoutGlobalScopes()->create([
            'company_id' => $a->id, 'tahun' => 2026, 'judul' => 'Milik Alfa',
        ]);
        TpkkpAssessment::withoutGlobalScopes()->create([
            'company_id' => $b->id, 'tahun' => 2026, 'judul' => 'Milik Beta',
        ]);

        $this->masuk($a);

        $this->assertSame(['Milik Alfa'], TpkkpAssessment::pluck('judul')->all());
    }

    /* ═══════════ perusahaan kedua tetap dapat mencatat ═══════════ */

    /**
     * Tanggal dan tahun yang sama pada dua perusahaan adalah keadaan
     * yang WAJAR, bukan bentrokan. Dua tambang menambang pada hari yang
     * sama, dan keduanya menilai kinerja keselamatan tahun yang sama.
     */
    public function test_dua_perusahaan_dapat_mencatat_tanggal_yang_sama(): void
    {
        [$a, $b] = $this->dua();

        foreach ([$a, $b] as $c) {
            EnergyProduction::withoutGlobalScopes()->create([
                'company_id' => $c->id, 'tanggal' => '2026-08-16', 'ton' => 5_000,
            ]);
            EnergyFuelRecon::withoutGlobalScopes()->create([
                'company_id' => $c->id, 'tanggal' => '2026-08-16', 'disalurkan_liter' => 500,
            ]);
            TpkkpAssessment::withoutGlobalScopes()->create([
                'company_id' => $c->id, 'tahun' => 2026, 'judul' => 'Penilaian '.$c->name,
            ]);
        }

        $this->assertSame(2, EnergyProduction::withoutGlobalScopes()->count());
        $this->assertSame(2, EnergyFuelRecon::withoutGlobalScopes()->count());
        $this->assertSame(2, TpkkpAssessment::withoutGlobalScopes()->count());
    }

    /**
     * Tetap satu baris per perusahaan per hari.
     *
     * Melonggarkan indeksnya tidak boleh menghilangkan aturannya:
     * produksi hari yang sama tercatat dua kali akan menggandakan
     * pembagi tanpa ada yang menambah data.
     */
    public function test_satu_perusahaan_tetap_tidak_boleh_kembar(): void
    {
        [$a] = $this->dua();

        EnergyProduction::withoutGlobalScopes()->create([
            'company_id' => $a->id, 'tanggal' => '2026-08-16', 'ton' => 5_000,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        EnergyProduction::withoutGlobalScopes()->create([
            'company_id' => $a->id, 'tanggal' => '2026-08-16', 'ton' => 9_999,
        ]);
    }

    /* ═══════════ pemilik terisi sendiri ═══════════ */

    /**
     * Baris baru mendapat perusahaan penulisnya tanpa disebut.
     *
     * Kolom yang ada tetapi tidak pernah terisi tidak menutup apa pun:
     * company_id NULL terbaca oleh SEMUA perusahaan di bawah scope ini.
     */
    public function test_baris_baru_mewarisi_perusahaan_penulisnya(): void
    {
        [$a] = $this->dua();
        $this->masuk($a);

        $p = EnergyProduction::create(['tanggal' => '2026-08-16', 'ton' => 5_000]);

        $this->assertSame($a->id, $p->company_id,
            'company_id dibiarkan kosong; barisnya akan terbaca seluruh perusahaan.');
    }
}
