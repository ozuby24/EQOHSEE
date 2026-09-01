<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\DasborGrafik;
use App\Support\DeretBulan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Deret bulan tidak boleh kehilangan bulan pada tanggal 29–31.
 *
 * ── CACAT YANG DIJAGA ──
 *
 * `now()->subMonths($i)` mempertahankan TANGGALNYA lalu meluap ketika
 * tanggal itu tidak ada di bulan tujuan:
 *
 *     31 Agustus − 6 bulan  →  31 Februari  →  meluap ke 3 Maret
 *     31 Agustus − 5 bulan  →  31 Maret
 *
 * Keduanya jatuh di Maret. Deret dua belas bulan yang disusun dari
 * tanggal 31 karena itu hanya menghasilkan TUJUH bulan berbeda.
 *
 * ── MENGAPA DIUJI DARI SETIAP TANGGAL ──
 *
 * Cacatnya hanya muncul tiga hari terakhir tiap bulan. Uji yang berjalan
 * memakai `now()` lulus dua puluh delapan hari lalu gagal tanpa ada yang
 * mengubah apa pun — dan kegagalan yang datang sendiri seperti itu
 * terbaca sebagai uji yang rewel, bukan sebagai cacat. Karena itu
 * tanggalnya disebut satu per satu di sini.
 */
class DeretBulanTest extends TestCase
{
    use RefreshDatabase;

    /** Tanggal yang menjadi pangkal, termasuk seluruh yang berisiko. */
    private function pangkal(): array
    {
        return [
            '2026-01-31', '2026-03-29', '2026-03-30', '2026-03-31',
            '2026-05-31', '2026-07-31', '2026-08-31', '2026-10-31',
            '2026-12-31', '2024-02-29',   // tahun kabisat
            '2026-08-01', '2026-08-15',   // pembanding yang memang aman
        ];
    }

    #[Test]
    public function deret_mundur_selalu_sepanjang_yang_diminta(): void
    {
        foreach ($this->pangkal() as $tgl) {
            foreach ([6, 12, 24] as $jumlah) {
                $kunci = DeretBulan::kunciMundur(Carbon::parse($tgl), $jumlah);

                $this->assertCount($jumlah, $kunci);

                $this->assertSame($jumlah, count(array_unique($kunci)),
                    "Dari {$tgl}, deret {$jumlah} bulan hanya menghasilkan "
                    .count(array_unique($kunci)).' bulan berbeda: '.implode(' ', $kunci));
            }
        }
    }

    #[Test]
    public function deret_maju_selalu_sepanjang_yang_diminta(): void
    {
        foreach ($this->pangkal() as $tgl) {
            $kunci = array_map(
                fn (Carbon $b) => $b->format('Y-m'),
                DeretBulan::maju(Carbon::parse($tgl), 12));

            $this->assertSame(12, count(array_unique($kunci)),
                "Dari {$tgl}, deret maju menghasilkan bulan berulang: ".implode(' ', $kunci));
        }
    }

    /** Urutannya menaik, dan bulan terakhirnya adalah bulan pangkalnya. */
    #[Test]
    public function deret_mundur_berakhir_pada_bulan_pangkalnya(): void
    {
        foreach ($this->pangkal() as $tgl) {
            $kunci = DeretBulan::kunciMundur($k = Carbon::parse($tgl), 12);

            $this->assertSame($k->format('Y-m'), end($kunci),
                "Deret dari {$tgl} tidak berakhir pada bulannya sendiri.");

            $urut = $kunci;
            sort($urut);
            $this->assertSame($urut, $kunci, "Deret dari {$tgl} tidak urut menaik.");
        }
    }

    /** Setiap butir jatuh pada tanggal 1 — tanggal yang ada di setiap bulan. */
    #[Test]
    public function tiap_butir_jatuh_pada_awal_bulan(): void
    {
        foreach (DeretBulan::mundur(Carbon::parse('2026-08-31'), 12) as $b) {
            $this->assertSame(1, $b->day,
                'Butir deret tidak pada tanggal 1 — ia masih dapat meluap.');
        }
    }

    /**
     * Grafik dasbor ikut terjaga.
     *
     * Diuji lewat pemanggilnya, bukan hanya lewat helpernya: helper yang
     * benar tidak menolong bila pemanggilnya masih menyusun deretnya
     * sendiri — dan itu persis keadaan sebelum perbaikan ini.
     */
    #[Test]
    public function grafik_hazard_bulanan_tidak_mengulang_label(): void
    {
        $g = DasborGrafik::hazardBulanan(Carbon::parse('2026-08-31'), 6);

        $this->assertCount(6, $g['label']);
        $this->assertSame(6, count(array_unique($g['label'])),
            'Grafik hazard bulanan mengulang label: '.implode(', ', $g['label']));
    }

    #[Test]
    public function grafik_sertifikat_jatuh_tempo_tidak_mengulang_label(): void
    {
        $g = DasborGrafik::sertifikatJatuhTempo(Carbon::parse('2026-01-31'), 6);

        $this->assertSame(6, count(array_unique($g['label'])),
            'Grafik jatuh tempo mengulang label: '.implode(', ', $g['label']));
    }

    /**
     * Dua halaman KPI ikut terjaga — dan dijaga dari TANGGAL YANG BERISIKO.
     *
     * Keduanya menyusun trennya dari `now()`, jadi uji yang berjalan pada
     * tanggal hari ini lulus dua puluh delapan hari sebulan meskipun
     * deretnya salah. Itu bukan penjagaan; itu undian. Waktu ujinya
     * karena itu dipatok ke 31 Agustus — tanggal tempat cacatnya paling
     * parah: dua belas bulan menyusut menjadi tujuh baris.
     */
    #[Test]
    public function tren_kpi_hazard_tetap_dua_belas_baris_pada_tanggal_31(): void
    {
        Carbon::setTestNow('2026-08-31 09:00:00');
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $tren = $this->get(route('hazard.analytics'))
            ->assertOk()->viewData('page')['props']['tren'];

        $this->assertCount(12, $tren,
            'Tren KPI hazard menyusut menjadi '.count($tren).' baris pada tanggal 31.');
    }

    #[Test]
    public function tren_evaluasi_temuan_tetap_dua_belas_baris_pada_tanggal_31(): void
    {
        Carbon::setTestNow('2026-08-31 09:00:00');
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $tren = $this->get(route('hazard.evaluasi'))
            ->assertOk()->viewData('page')['props']['tren'];

        $this->assertCount(12, $tren,
            'Tren evaluasi temuan menyusut menjadi '.count($tren).' baris pada tanggal 31.');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
