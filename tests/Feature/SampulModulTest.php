<?php

namespace Tests\Feature;

use App\Models\{Company, MineMapLayer, User, WaterLog, WaterSump};
use App\Support\{KondisiSitus, Media, Menu, SampulModul};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Sampul halaman awal modul: foto, geo tag, dan kondisi cuaca.
 */
class SampulModulTest extends TestCase
{
    use RefreshDatabase;

    /* ---------- berkas dan sebarannya ---------- */

    public function test_setiap_berkas_sampul_yang_dipetakan_benar_benar_ada(): void
    {
        foreach (SampulModul::berkasDipakai() as $nama) {
            $this->assertTrue(
                Media::ada("sampul/{$nama}.jpg"),
                "Sampul '{$nama}.jpg' dipetakan tetapi berkasnya tidak ada di public/media/sampul.",
            );
        }
    }

    /**
     * Yang dijaga bukan sekadar "ada gambarnya", melainkan bahwa
     * gambarnya BERBEDA-BEDA. Satu foto untuk seluruh modul membuat
     * kedua puluh halaman awalnya terbaca sebagai halaman yang sama.
     */
    public function test_sampul_tersebar_bukan_satu_gambar_untuk_semua(): void
    {
        $dipakai = array_values(SampulModul::peta());

        $this->assertGreaterThanOrEqual(
            5,
            count(array_unique($dipakai)),
            'Sampul modul terlalu seragam — halaman awal tiap modul jadi tidak terbedakan.',
        );
    }

    public function test_setiap_modul_pada_menu_mendapat_sampul(): void
    {
        foreach (array_keys(Menu::all()) as $kunci) {
            $sampul = SampulModul::untuk($kunci);

            $this->assertNotNull($sampul, "Modul '{$kunci}' tidak mendapat sampul.");
            $this->assertNotSame('', $sampul['keterangan'],
                "Sampul modul '{$kunci}' tanpa keterangan — foto umum mudah disangka foto situs sendiri.");
        }
    }

    public function test_modul_tak_dikenal_memakai_sampul_bawaan_bukan_kosong(): void
    {
        $this->assertNotNull(SampulModul::untuk('modul-yang-belum-ada'));
    }

    /**
     * Berkasnya dibaca lewat Media, jadi pemasangan yang belum menyalin
     * fotonya tidak menampilkan gambar rusak — sampulnya hanya tidak ada.
     */
    public function test_tanpa_berkas_sampul_tidak_memaksakan_gambar_rusak(): void
    {
        config(['media.akar' => 'media-yang-tidak-ada']);

        $this->assertNull(SampulModul::untuk('air'));
    }

    /* ---------- klasifikasi hujan ---------- */

    public function test_kelas_hujan_mengikuti_ambang_bmkg(): void
    {
        $harapan = [
            [0.0,   'Cerah'],
            [0.4,   'Cerah'],
            [0.5,   'Hujan Ringan'],
            [19.9,  'Hujan Ringan'],
            [20.0,  'Hujan Sedang'],
            [49.9,  'Hujan Sedang'],
            [50.0,  'Hujan Lebat'],
            [99.9,  'Hujan Lebat'],
            [100.0, 'Hujan Sangat Lebat'],
        ];

        foreach ($harapan as [$mm, $label]) {
            $this->assertSame($label, KondisiSitus::kelasHujan($mm)['label'], "{$mm} mm");
        }
    }

    public function test_curah_hujan_negatif_diperlakukan_seperti_nol(): void
    {
        $this->assertSame('Cerah', KondisiSitus::kelasHujan(-5)['label']);
    }

    /* ---------- cuaca dibaca dari catatan, bukan dikarang ---------- */

    private function perusahaan(): Company
    {
        return Company::create(['name' => 'PT Situs Uji', 'code' => 'PSU', 'location' => 'Site Sangatta']);
    }

    private function catatHujan(Company $c, float $mm, string $tanggal): void
    {
        $sump = WaterSump::create([
            'company_id' => $c->id, 'kode' => 'KP-1', 'nama' => 'Kolam 1', 'jenis' => 'settling',
        ]);

        WaterLog::create([
            'company_id' => $c->id, 'water_sump_id' => $sump->id,
            'tanggal' => $tanggal, 'curah_hujan_mm' => $mm,
        ]);
    }

    /**
     * Yang paling penting dijaga di sini: TANPA catatan, tidak boleh ada
     * lencana "Cerah". Lencana itu terbaca sebagai bacaan alat, dan orang
     * mengambil keputusan lapangan dari bacaan alat.
     */
    public function test_tanpa_catatan_hujan_tidak_mengaku_cerah(): void
    {
        $this->assertNull(KondisiSitus::cuaca($this->perusahaan()));
    }

    public function test_cuaca_dibaca_dari_catatan_hujan_situs_sendiri(): void
    {
        $c = $this->perusahaan();
        $this->catatHujan($c, 62.5, now()->toDateString());

        $cuaca = KondisiSitus::cuaca($c);

        $this->assertSame('Hujan Lebat', $cuaca['label']);
        $this->assertSame(62.5, $cuaca['hujanMm']);
        $this->assertTrue($cuaca['hariIni']);
    }

    /**
     * Catatan minggu lalu bukan cuaca hari ini. Tanpa batas umur, sampul
     * menyebut "Hujan Lebat" pada hari yang terik — dan justru situs yang
     * pencatatannya paling jarang yang paling sering salah disebut.
     */
    public function test_catatan_yang_sudah_basi_tidak_dipakai(): void
    {
        $c = $this->perusahaan();
        $this->catatHujan($c, 80, now()->subDays(9)->toDateString());

        $this->assertNull(KondisiSitus::cuaca($c));
    }

    public function test_cuaca_perusahaan_lain_tidak_ikut_terbaca(): void
    {
        $a = $this->perusahaan();
        $b = Company::create(['name' => 'PT Situs Lain', 'code' => 'PSL']);

        $this->catatHujan($b, 95, now()->toDateString());

        $this->assertNull(KondisiSitus::cuaca($a), 'Hujan di situs lain bukan hujan di situs ini.');
    }

    /* ---------- geo tag ---------- */

    public function test_geo_tag_memakai_nama_lokasi_perusahaan(): void
    {
        $lokasi = KondisiSitus::lokasi($this->perusahaan());

        $this->assertSame('Site Sangatta', $lokasi['nama']);
        $this->assertNull($lokasi['koordinat'], 'Tanpa layer peta, koordinatnya tidak dikarang.');
    }

    public function test_koordinat_diambil_dari_titik_tengah_layer_peta(): void
    {
        $c = $this->perusahaan();

        MineMapLayer::create([
            'company_id' => $c->id, 'nama' => 'Pit Utama', 'tipe' => 'pit',
            'geojson' => json_encode([
                'type' => 'Polygon',
                'coordinates' => [[[117.5, -0.5], [117.6, -0.5], [117.6, -0.4], [117.5, -0.4], [117.5, -0.5]]],
            ]),
        ]);

        $lokasi = KondisiSitus::lokasi($c->fresh());

        $this->assertNotNull($lokasi['koordinat']);
        $this->assertStringContainsString('LS', $lokasi['koordinat'], 'Lintang selatan untuk lintang negatif.');
        $this->assertStringContainsString('BT', $lokasi['koordinat'], 'Bujur timur untuk bujur positif.');
    }

    public function test_tanpa_perusahaan_tidak_ada_keterangan_situs(): void
    {
        $this->assertNull(KondisiSitus::untuk(User::factory()->create(['company_id' => null])));
    }

    /* ---------- pemasangan di halaman ---------- */

    public function test_sampul_muncul_di_halaman_awal_modul(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('pjp.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('sampul.gambar')
                ->where('sampul.label', 'Perusahaan Jasa Pertambangan'));
    }

    /**
     * Di subhalaman ia tidak menjawab apa pun lagi — hanya menggeser isi
     * ke bawah pada tiap formulir yang dibuka.
     */
    public function test_sampul_tidak_diulang_pada_subhalaman(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('pjp.daftar'))
            ->assertInertia(fn (Assert $page) => $page->where('sampul', null));
    }

    /**
     * Tiap halaman awal modul harus benar-benar mengirim sampulnya.
     * Yang tertinggal tidak menimbulkan galat — halamannya hanya
     * diam-diam kembali polos sementara modul lain bersampul.
     */
    public function test_seluruh_halaman_awal_modul_inertia_mengirim_sampul(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $tertinggal = [];

        foreach (Menu::all() as $kunci => $modul) {
            $rute = Menu::ruteAwal($modul);

            if ($rute === null || !Route::has($rute)) continue;
            if (!\App\Support\RuteInertia::ada($rute)) continue;   // halaman Blade diuji terpisah

            /* Prop dibaca dari data halaman Inertia pada tanggapan penuh,
               bukan dari JSON: permintaan biasa mengembalikan HTML. */
            $halaman = $this->get(route($rute, [], false))->viewData('page');
            $prop = $halaman['props']['sampul'] ?? null;

            if (!is_array($prop) || !isset($prop['gambar'])) $tertinggal[] = "{$kunci} ({$rute})";
        }

        $this->assertSame([], $tertinggal,
            "Halaman awal modul berikut tidak mengirim sampul:\n  ".implode("\n  ", $tertinggal));
    }
}
