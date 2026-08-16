<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use App\Support\{Keandalan, Ko};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Angka yang dihitung dari data yang tidak ada.
 *
 * Nol dibagi nol bukan seratus persen dan bukan nol persen — ia
 * ketiadaan ukuran. Ketiganya harus terbaca berbeda, dan pada modul
 * kepatuhan pertambangan perbedaan itu bukan kehalusan: angka di
 * layar dapat ditangkap layar lalu masuk ke laporan kepada inspektur
 * tambang.
 *
 * Semua yang diuji di sini pernah salah sekaligus, dan tidak satu pun
 * ketahuan oleh uji mana pun — sebab seluruh uji yang ada menyiapkan
 * datanya lebih dulu. Yang menemukannya adalah tangkapan layar
 * pemasangan sungguhan yang registernya masih kosong:
 *
 *   "Belum ada alat terdaftar" · KEPATUHAN PM 100,0%
 *   "0 dari 0 perangkat pengaman berfungsi" · 100%
 *   "0 dari 0 objek PM sesuai jadwal" · 0%
 *
 * Dua angka terakhir menggambarkan keadaan yang sama persis dengan dua
 * jawaban yang berlawanan.
 */
class AngkaTanpaDataTest extends TestCase
{
    use RefreshDatabase;

    /* ═══════════ rentang hari ═══════════ */

    /**
     * Satu bulan penuh adalah 31 hari, bukan 32.
     *
     * `sampai` dijepit ke AKHIR hari, jadi selisihnya 30,99999… hari.
     * Versi lama membulatkannya menjadi 31 lalu menambah 1 untuk
     * inklusivitas — menghitung hari terakhirnya dua kali. Akibatnya
     * bukan hanya angka "Hari" yang salah di layar: setiap indikator
     * "per hari" dibagi dengan penyebut yang kelebihan satu.
     */
    public function test_rentang_sebulan_penuh_terhitung_31_hari(): void
    {
        $c = Company::create(['name' => 'PT Hari']);
        $u = User::factory()->create([
            'is_admin' => true, 'company_id' => $c->id, 'email_verified_at' => now(),
        ]);

        $props = $this->actingAs($u)
            ->get(route('energi.index', ['dari' => '2026-08-01', 'sampai' => '2026-08-31']))
            ->viewData('page')['props'];

        $hari = $props['r']['hari'] ?? null;

        $this->assertSame(31, $hari,
            'Rentang 1–31 Agustus terhitung '.var_export($hari, true).' hari.');
    }

    /** Satu hari tetap satu hari, bukan nol maupun dua. */
    public function test_rentang_satu_hari_terhitung_satu(): void
    {
        $c = Company::create(['name' => 'PT Hari']);
        $u = User::factory()->create([
            'is_admin' => true, 'company_id' => $c->id, 'email_verified_at' => now(),
        ]);

        $props = $this->actingAs($u)
            ->get(route('energi.index', ['dari' => '2026-08-05', 'sampai' => '2026-08-05']))
            ->viewData('page')['props'];

        $this->assertSame(1, $props['r']['hari'] ?? null);
    }

    /* ═══════════ keandalan armada ═══════════ */

    /**
     * Tanpa alat berjadwal, kepatuhan PM tidak ada — bukan 100%.
     *
     * Docblock fungsinya sendiri sudah menalar benar ("alat tanpa
     * jadwal bukan alat yang patuh maupun tidak patuh") dan nilai
     * baliknya dahulu melanggarnya.
     */
    public function test_kepatuhan_pm_tanpa_alat_tidak_terukur(): void
    {
        $hasil = Keandalan::kepatuhanPm(new Collection(), now());

        $this->assertNull($hasil['persen'],
            'Armada kosong melaporkan kepatuhan PM sebagai angka. '
            .'"100%" pada register yang belum berisi apa pun dapat masuk laporan kepatuhan.');

        $this->assertSame(0, $hasil['berjadwal']);
    }

    /** Dengan alat berjadwal, angkanya tetap dihitung seperti biasa. */
    public function test_kepatuhan_pm_tetap_dihitung_bila_ada_alat(): void
    {
        $alat = new Collection([
            (object) ['pm_berikutnya' => now()->addDays(10)->toDateString()],
            (object) ['pm_berikutnya' => now()->addDays(20)->toDateString()],
            (object) ['pm_berikutnya' => now()->subDays(5)->toDateString()],   // terlambat
        ]);

        $hasil = Keandalan::kepatuhanPm($alat, now());

        $this->assertSame(3, $hasil['berjadwal']);
        $this->assertSame(1, $hasil['terlambat']);
        $this->assertEqualsWithDelta(66.7, $hasil['persen'], 0.1);
    }

    /* ═══════════ keselamatan operasi ═══════════ */

    public function test_persen_tanpa_penyebut_tidak_terukur(): void
    {
        $this->assertNull(Ko::persen(0, 0));
        $this->assertSame(100, Ko::persen(5, 5));
        $this->assertSame(0,   Ko::persen(0, 5));
    }

    /**
     * Register KO yang kosong: seluruh sub-elemen tidak terukur, dan
     * indeks gabungannya juga tidak ada.
     *
     * Dahulu tiga sub-elemen memulangkan 100 dan dua memulangkan 0,
     * sehingga register kosong menghasilkan indeks 60% — dirata-ratakan
     * dari lima angka yang tak satu pun berasal dari data.
     */
    public function test_register_ko_kosong_tidak_menghasilkan_indeks(): void
    {
        $c = Ko::hitung(new Collection(), new Collection());

        foreach (['pmc', 'pgPct', 'kjPct', 'tnPct', 'layakPct'] as $kunci) {
            $this->assertNull($c[$kunci], "Ko::hitung()['{$kunci}'] mengarang angka dari register kosong.");
        }

        $sub = Ko::subElemen($c);

        $this->assertNull($sub['indeks'],
            'Indeks kepatuhan KO terbentuk dari register yang tidak berisi apa pun.');

        $this->assertSame(0, $sub['terukur']);

        foreach ($sub['items'] as $item) {
            $this->assertNull($item['pct'], "Sub-elemen \"{$item['nama']}\" mengarang angka.");
        }
    }

    /**
     * Indeks dihitung HANYA dari sub-elemen yang terukur.
     *
     * Yang belum diisi tidak boleh dihitung sebagai nol — itu menghukum
     * perusahaan karena belum memakai modulnya — dan tidak boleh
     * dihitung sebagai seratus, sebab itu memberi nilai yang tidak
     * diperolehnya.
     */
    public function test_indeks_mengabaikan_yang_belum_terukur(): void
    {
        $sub = Ko::subElemen([
            'pmc' => 80, 'pgPct' => 60, 'kjPct' => null, 'tnPct' => null, 'layakPct' => null,
            'total' => 5, 'overdue' => 1, 'pgOk' => 3, 'pgTot' => 5,
            'kjLap' => 0, 'kjTot' => 0, 'tnAktif' => 0, 'tnTot' => 0,
            'byStat' => [Ko::ST_LAYAK => 0],
        ]);

        $this->assertSame(2, $sub['terukur']);
        $this->assertSame(70, $sub['indeks'], 'Yang belum terukur ikut menarik rata-ratanya.');
    }
}
