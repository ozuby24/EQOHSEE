<?php

namespace Tests\Feature;

use App\Models\{Company, MineMapLayer, User, WaterLog, WaterSump};
use App\Support\{KondisiSitus, Media, Menu, SampulModul, Waktu};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
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

    /**
     * Meter intensitas pada sampul membaca skala ini. Skala yang urutannya
     * terbalik membuat hujan sangat lebat tergambar sebagai satu kotak
     * menyala dan cerah sebagai lima — persis kebalikan dari artinya,
     * tanpa satu pun galat muncul.
     */
    public function test_skala_hujan_urut_dari_teringan_ke_terberat(): void
    {
        $urut = array_column(KondisiSitus::skala(), 'kunci');

        $this->assertSame(
            ['cerah', 'hujan_ringan', 'hujan_sedang', 'hujan_lebat', 'hujan_sangat_lebat'],
            $urut,
        );
    }

    public function test_tingkat_menunjuk_kedudukan_pada_skala(): void
    {
        $this->assertSame(0, KondisiSitus::tingkat('cerah'));
        $this->assertSame(4, KondisiSitus::tingkat('hujan_sangat_lebat'));

        // Kunci tak dikenal jatuh ke tingkat teringan, bukan ke luar batas
        // larik — meter yang menerima indeks -1 tidak menggambar apa pun.
        $this->assertSame(0, KondisiSitus::tingkat('entah'));
    }

    public function test_cuaca_membawa_tingkat_dan_panjang_skala_untuk_meter(): void
    {
        $c = $this->perusahaan();
        $this->catatHujan($c, 120, $this->hariIniLokal());

        $cuaca = KondisiSitus::cuaca($c);

        $this->assertSame(4, $cuaca['tingkat']);
        $this->assertSame(5, $cuaca['skala']);
        $this->assertLessThan($cuaca['skala'], $cuaca['tingkat'],
            'Tingkat harus selalu di dalam panjang skalanya.');
    }

    /* ---------- cuaca dibaca dari catatan, bukan dikarang ---------- */

    private function perusahaan(): Company
    {
        return Company::create(['name' => 'PT Situs Uji', 'code' => 'PSU', 'location' => 'Site Sangatta']);
    }

    /**
     * Tanggal "hari ini" MENURUT SITUSNYA, bukan menurut UTC.
     *
     * Catatan hujan bertanggal kalender di lokasi tambang, dan lencana
     * cuaca menyebut sebuah catatan "hari ini" dengan membandingkannya
     * ke waktu lokal — WITA, delapan jam di depan UTC. Menulis
     * `now()->toDateString()` di sini menyimpan tanggal UTC, sehingga
     * sejak pukul 16.00 UTC catatannya tersimpan bertanggal kemarin
     * menurut situsnya dan lencana "hari ini" tidak muncul.
     *
     * Uji ini karena itu gagal sendiri sepertiga hari, setiap hari,
     * tanpa satu baris pun diubah — kegagalan yang datang sendiri
     * seperti itu terbaca sebagai uji rewel lalu ditandai lewati, dan
     * bersamanya ikut hilang penjagaan atas hal yang sungguh diuji di
     * sini: lencana cuaca hanya boleh mengaku tahu bila ada catatannya.
     */
    private function hariIniLokal(int $mundur = 0): string
    {
        return Waktu::kini()->subDays($mundur)->toDateString();
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
        $this->catatHujan($c, 62.5, $this->hariIniLokal());

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
        $this->catatHujan($c, 80, $this->hariIniLokal(9));

        $this->assertNull(KondisiSitus::cuaca($c));
    }

    public function test_cuaca_perusahaan_lain_tidak_ikut_terbaca(): void
    {
        $a = $this->perusahaan();
        $b = Company::create(['name' => 'PT Situs Lain', 'code' => 'PSL']);

        $this->catatHujan($b, 95, $this->hariIniLokal());

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

    /**
     * Lapisan peta yang digambar pada bidang lokal berpusat nol tidak
     * boleh keluar sebagai geo tag.
     *
     * Titik sedekat itu ke (0, 0) menunjuk Teluk Guinea, bukan sebuah
     * tambang — selalu berarti petanya belum diikat ke koordinat
     * sesungguhnya. Berdampingan dengan nama situs ia terbaca sebagai
     * koordinat situs itu, dan lembar yang dicetak membawanya keluar.
     * Yang kosong terlihat sebagai belum diisi; yang salah tidak
     * terlihat apa-apa.
     */
    public function test_titik_peta_di_null_island_tidak_dipakai_sebagai_geo_tag(): void
    {
        $c = $this->perusahaan();

        MineMapLayer::create([
            'company_id' => $c->id, 'nama' => 'Batas Pit Utara', 'tipe' => 'pit',
            'geojson' => json_encode([
                'type' => 'Polygon',
                'coordinates' => [[[0.0, 0.0], [0.008, 0.0], [0.008, 0.008], [0.0, 0.008], [0.0, 0.0]]],
            ]),
        ]);

        $lokasi = KondisiSitus::lokasi($c->fresh());

        $this->assertSame('Site Sangatta', $lokasi['nama'],
            'Nama situsnya tetap disebut — yang dibuang hanya koordinatnya.');
        $this->assertNull($lokasi['koordinat'],
            'Titik di sekitar (0,0) berarti belum berkoordinat, bukan berkoordinat nol.');
    }

    /**
     * Kolom yang dipakai KondisiSitus harus BENAR-BENAR ADA.
     *
     * Uji fungsional tidak dapat menangkap ini, dan itu bukan kelalaian
     * melainkan perbedaan dialek. Laravel mengutip pengenal dengan kutip
     * ganda pada SQLite, dan SQLite memperlakukan kutip ganda yang tidak
     * cocok dengan kolom mana pun sebagai STRING BIASA: `order by
     * "luas_ha"` menjadi pengurutan terhadap tetapan — berhasil, tanpa
     * berefek, tanpa satu pun peringatan. MySQL memakai backtick dan
     * menolaknya dengan "Unknown column".
     *
     * Terukur: `luas_ha` (milik tabel reklamasi) tertulis pada query
     * `mine_map_layers`, lolos 2.200 uji beserta penelusuran peramban di
     * SQLite, lalu menjawab 500 pada SETIAP halaman awal modul di
     * produksi yang memakai MySQL.
     *
     * Nama kolomnya dibaca DARI berkasnya, bukan ditulis ulang di sini —
     * daftar yang ditulis ulang akan tetap cocok dengan dirinya sendiri
     * meski querynya berubah.
     */
    public function test_kolom_yang_diurutkan_kondisi_situs_ada_di_tabelnya(): void
    {
        $isi = file_get_contents(app_path('Support/KondisiSitus.php'));

        preg_match_all(
            "/->(?:orderBy|orderByDesc)\(\s*'([a-z_][a-z0-9_]*)'/i",
            $isi, $m,
        );

        $this->assertNotEmpty($m[1], 'Tidak ada pengurutan yang terbaca — polanya perlu disesuaikan.');

        $hilang = [];

        foreach (array_unique($m[1]) as $kolom) {
            $adaDiSalahSatu = false;

            foreach (['mine_map_layers', 'water_logs'] as $tabel) {
                if (Schema::hasColumn($tabel, $kolom)) { $adaDiSalahSatu = true; break; }
            }

            if (!$adaDiSalahSatu) $hilang[] = $kolom;
        }

        sort($hilang);

        $this->assertSame([], $hilang,
            "KondisiSitus mengurutkan memakai kolom yang tidak ada:\n  ".implode("\n  ", $hilang)
            ."\nDi SQLite ini lolos diam-diam — kutip ganda yang tak dikenal dibaca sebagai "
            ."string — sedangkan MySQL menjawab \"Unknown column\", dan setiap halaman awal "
            ."modul menjadi 500.");
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
     * Subhalaman membawa FOTO modulnya, tanpa bilah keadaan situs.
     *
     * Aturannya sempat sebaliknya — subhalaman tidak membawa sampul sama
     * sekali — dan akibatnya seluruh subhalaman di dua puluh modul
     * memakai satu foto merek yang sama: begitu orangnya menekan butir
     * menu kedua, modul mana pun terlihat persis sama.
     *
     * Keberatan aslinya tetap dijaga, dan letaknya memang bukan pada
     * fotonya melainkan pada TINGGINYA: `kondisi` null di sini, dan
     * itulah yang membuat KopHalaman menggambar kop pendek alih-alih
     * kop bersitus setinggi 212px. Uji di bawah menjaga keduanya
     * sekaligus.
     */
    public function test_subhalaman_membawa_foto_modulnya_tanpa_bilah_keadaan(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('pjp.daftar'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('sampul.gambar')
                ->where('sampul.kondisi', null));
    }

    /**
     * Foto subhalaman adalah foto MODULNYA, bukan foto modul lain.
     *
     * Pemetaan yang meleset tidak menimbulkan galat: halamannya tetap
     * bergambar, hanya bergambar milik modul yang salah — dan itu justru
     * lebih menyesatkan daripada tidak bergambar sama sekali.
     */
    public function test_foto_subhalaman_mengikuti_modulnya_sendiri(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('pjp.daftar'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('sampul.gambar',
                    fn ($g) => str_contains((string) $g, \App\Support\SampulModul::nama('pjp'))));
    }

    /**
     * Bilah keadaan situs TIDAK dihitung pada subhalaman.
     *
     * Bukan sekadar tidak digambar: KondisiSitus menyentuh basis data
     * dan layanan cuaca, dan membayarnya pada tiap permintaan demi bilah
     * yang tidak muncul adalah biaya yang tidak dibelanjakan untuk apa
     * pun. Yang dijaga di sini hasilnya — null — sebab itulah satu-
     * satunya bukti yang tidak ikut berubah bila cara menghitungnya
     * diganti.
     */
    public function test_halaman_awal_tetap_membawa_bilah_keadaan(): void
    {
        $c = Company::create([
            'name' => 'PT Situs Berkondisi', 'code' => 'PSB',
            'location' => 'Kutai Timur, Kalimantan Timur',
        ]);

        $this->actingAs(User::factory()->create(['is_admin' => true, 'company_id' => $c->id]));

        $this->get(route('pjp.index'))
            ->assertInertia(fn (Assert $page) => $page->has('sampul.kondisi'));
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
