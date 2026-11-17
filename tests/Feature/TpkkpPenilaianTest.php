<?php

namespace Tests\Feature;

use App\Models\{TpkkpAssessment, User};
use App\Support\Tpkkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * PTPKKP — halaman penilaian (Inertia).
 *
 * Halaman pertama yang dipindah dari Blade ke Vue. Ujinya berbeda
 * bentuknya dari halaman Blade lain: yang diperiksa bukan HTML yang
 * terkirim melainkan prop yang dikirim ke komponen, sebab HTML-nya baru
 * terbentuk di peramban. `assertSee` di sini akan lulus untuk alasan yang
 * salah — teksnya memang tidak ada di badan tanggapan.
 */
class TpkkpPenilaianTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['is_admin' => true]);
        $this->actingAs($u);

        return $u;
    }

    /* ══════════════ prop halaman ══════════════ */

    public function test_halaman_dirender_inertia_bukan_blade(): void
    {
        $this->admin();

        $this->get('/tpkkp/penilaian')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Tpkkp/Penilaian'));
    }

    public function test_prop_halaman_lengkap(): void
    {
        $this->admin();

        $this->get('/tpkkp/penilaian')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Tpkkp/Penilaian')
                ->has('judul')
                ->has('subjudul')
                ->has('tahun')
                ->has('metode')
                ->has('metodeAktif')
                ->has('parameter')
                ->has('entitas')
                ->has('items')
                ->has('ambang')
                ->where('bisaSunting', true)
            );
    }

    public function test_tiap_item_membawa_sel_nilai_dan_rubriknya(): void
    {
        $this->admin();

        $this->get('/tpkkp/penilaian')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('items.0', fn (AssertableInertia $i) => $i
                    ->has('kode')->has('nama')->has('metode')->has('maks')
                    ->has('nilai')->has('ket')->has('rubrik')->has('target')
                )
            );
    }

    /* ══════════════ ambang kategori ══════════════ */

    /**
     * Ambang kategori harus datang dari server, bukan ditulis di klien.
     *
     * Berkas Vue sempat memuat salinan yang ditulis tangan, dan isinya
     * keliru seluruhnya — batas maupun nama tingkatnya tidak ada di acuan.
     * Cacat seperti itu tidak menimbulkan galat: lencananya hanya menyebut
     * tingkat kematangan yang salah, dan justru itu yang dibaca orang.
     */
    public function test_ambang_kategori_dikirim_persis_seperti_acuan(): void
    {
        $this->admin();

        $ambang = $this->get('/tpkkp/penilaian')->assertOk()
                       ->viewData('page')['props']['ambang'];

        $acuan = Tpkkp::ref()['thresholds'];

        $this->assertCount(count($acuan), $ambang);

        foreach ($acuan as $i => $t) {
            $this->assertEqualsWithDelta((float) $t['lt'], $ambang[$i]['batas'], 1e-9);
            $this->assertSame($t['label'], $ambang[$i]['label']);
        }
    }

    public function test_tidak_ada_ambang_yang_ditulis_ulang_di_berkas_vue(): void
    {
        $vue = file_get_contents(resource_path('js/Pages/Tpkkp/Penilaian.vue'));

        // Nama tingkat kematangan tidak boleh muncul sebagai teks di klien;
        // kalau muncul, berarti ada salinan daftar ambang di sana lagi.
        foreach (Tpkkp::LV as $tingkat) {
            $this->assertStringNotContainsString("'{$tingkat}'", $vue,
                "Tingkat '{$tingkat}' tertulis di berkas Vue — ambang harus datang dari server.");
        }
    }

    /* ══════════════ penyaring ══════════════ */

    public function test_metode_dan_parameter_dapat_dipilih(): void
    {
        $this->admin();

        $metode = array_key_first(Tpkkp::methods());

        $this->get('/tpkkp/penilaian?m='.$metode)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('metodeAktif', $metode));
    }

    public function test_metode_tak_dikenal_jatuh_ke_metode_pertama(): void
    {
        $this->admin();

        $this->get('/tpkkp/penilaian?m=TIDAK-ADA')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) =>
                $p->where('metodeAktif', array_key_first(Tpkkp::methods())));
    }

    public function test_seluruh_item_yang_dikirim_memakai_metode_aktif(): void
    {
        // Item yang tidak memakai metode ini akan tampil dengan sel nilai
        // yang tidak berarti apa-apa bagi penilainya.
        $this->admin();

        $props = $this->get('/tpkkp/penilaian')->assertOk()->viewData('page')['props'];

        $this->assertNotEmpty($props['items']);
        foreach ($props['items'] as $it) {
            $this->assertContains($props['metodeAktif'], $it['metode'],
                "Item {$it['kode']} tidak memakai metode {$props['metodeAktif']}.");
        }
    }

    /* ══════════════ penyimpanan ══════════════ */

    public function test_nilai_tersimpan_lewat_bentuk_kiriman_yang_sama(): void
    {
        // Bentuk kiriman sengaja tidak diubah saat halaman dipindah ke Vue,
        // supaya saveAssess() tetap melayani kedua tampilan.
        $this->admin();

        $props = $this->get('/tpkkp/penilaian')->assertOk()->viewData('page')['props'];
        $item  = $props['items'][0];
        $sel   = array_key_first($item['nilai']);

        $this->post('/tpkkp/penilaian?tahun='.$props['tahun'], [
            'metode' => $props['metodeAktif'],
            'n'      => [$item['kode'] => [$sel => 4]],
            'ket'    => [$item['kode'] => 'Bukti dokumen terlampir.'],
        ])->assertRedirect();

        $a = TpkkpAssessment::forYear($props['tahun']);
        $this->assertSame(4, $sel === '_'
            ? $a->cell($props['metodeAktif'], $item['kode'])
            : $a->cell($props['metodeAktif'], $item['kode'], $sel));
        $this->assertSame('Bukti dokumen terlampir.', $a->ket($props['metodeAktif'], $item['kode']));
    }

    public function test_nilai_tersimpan_terbaca_kembali_pada_prop(): void
    {
        $this->admin();

        $props = $this->get('/tpkkp/penilaian')->assertOk()->viewData('page')['props'];
        $item  = $props['items'][0];
        $sel   = array_key_first($item['nilai']);

        $this->post('/tpkkp/penilaian?tahun='.$props['tahun'], [
            'metode' => $props['metodeAktif'],
            'n'      => [$item['kode'] => [$sel => 3]],
        ]);

        $lagi = $this->get('/tpkkp/penilaian')->assertOk()->viewData('page')['props'];
        $this->assertSame(3, collect($lagi['items'])->firstWhere('kode', $item['kode'])['nilai'][$sel]);
    }

    public function test_pengguna_biasa_tidak_dapat_menyimpan(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->post('/tpkkp/penilaian', ['metode' => array_key_first(Tpkkp::methods())])
             ->assertForbidden();
    }

    public function test_pengguna_biasa_membuka_halaman_tanpa_izin_sunting(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/tpkkp/penilaian')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('bisaSunting', false));
    }

    public function test_tamu_tidak_dapat_membuka_halaman(): void
    {
        $this->get('/tpkkp/penilaian')->assertRedirect(route('login'));
    }

    /* ══════════════ data bersama ══════════════ */

    public function test_menu_bersama_sama_isinya_dengan_bilah_samping_blade(): void
    {
        // Keduanya membaca App\Support\Menu; kalau salah satu menyusun
        // sendiri, menunya akan berbeda antara halaman Blade dan Vue tanpa
        // ada galat yang muncul.
        $u = $this->admin();

        $props = $this->get('/tpkkp/penilaian')->assertOk()->viewData('page')['props'];

        $this->assertSame(
            count(\App\Support\Menu::untuk($u)),
            count($props['menu']['modul']),
            'Jumlah modul pada menu Inertia harus sama dengan katalog menu.'
        );

        $aktif = collect($props['menu']['modul'])->firstWhere('aktif', true);
        $this->assertSame('tpkkp', $aktif['kunci']);
    }

    public function test_data_bersama_membawa_pengguna_dan_tema(): void
    {
        $this->admin();

        $this->get('/tpkkp/penilaian')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('pengguna.nama')
                ->has('warna.aksen')
                ->has('pengumuman')
            );
    }

    /* ══════════════ kelas Tailwind dari komponen Vue ══════════════ */

    /**
     * Tailwind harus memindai .vue, bukan hanya .blade.php.
     *
     * `tailwind.config.js` sempat hanya mendaftar `resources/views/**\/*.blade.php`.
     * Kelas yang cuma muncul di sebuah komponen Vue dibuang saat build
     * produksi tanpa galat apa pun — kelasnya sekadar hilang dari CSS
     * terkompilasi. `disabled:cursor-not-allowed` pada tombol Simpan
     * PTPKKP adalah korban nyatanya: kelas itu tidak muncul di satu pun
     * berkas Blade, jadi build produksi membuangnya sepenuhnya.
     */
    public function test_tailwind_memindai_berkas_vue(): void
    {
        $konfig = file_get_contents(base_path('tailwind.config.js'));

        $this->assertStringContainsString('resources/js/**/*.vue', $konfig,
            'tailwind.config.js tidak memindai .vue — kelas yang hanya '.
            'dipakai komponen Vue akan dibuang saat build produksi.');
    }

    /* ══════════════ halaman Blade tidak terganggu ══════════════ */

    public function test_halaman_tpkkp_lain_tetap_blade(): void
    {
        // Middleware Inertia dipasang pada seluruh rute web; halaman yang
        // tidak memanggil Inertia::render() harus tetap mengirim HTML utuh.
        $this->admin();

        foreach (['/tpkkp', '/tpkkp/rekap'] as $url) {
            $this->get($url)->assertOk()->assertSee('<!DOCTYPE html>', false);
        }
    }

    /* ══════════════ tingkat rubrik vs kategori rasio ══════════════ */

    /**
     * Tingkat yang tampil harus sama dengan tingkat yang diisi.
     *
     * Dua skala hidup berdampingan di modul ini dan bernama sama:
     *
     *   kategori — dari RASIO capaian (nilai/maks), ambang workbook resmi
     *   tingkat  — dari SKOR RUBRIK 1–5 yang benar-benar diisi penilai
     *
     * Keduanya sah, tetapi tidak selaras. Mengisi 3 pada seluruh metode
     * memberi rasio 0,6 — yang menurut ambang rasio jatuh ke "Reaktif",
     * padahal yang dinilai adalah "Terencana". Mengisi 2 menampilkan
     * "Dasar".
     *
     * Cacat semacam ini tidak menimbulkan galat apa pun. Lencananya hanya
     * menyebut tingkat kematangan yang salah — dan itulah satu-satunya
     * angka yang benar-benar dibaca orang pada formulir penilaian.
     */
    public function test_tingkat_sama_dengan_yang_diisi(): void
    {
        $item = ['code' => '1.1.1', 'name' => 'Uji', 'methods' => ['TD', 'FGD', 'KS']];

        foreach ([1 => 'Dasar', 2 => 'Reaktif', 3 => 'Terencana', 4 => 'Proaktif', 5 => 'Resilient'] as $isi => $label) {
            $scores = [];
            foreach ($item['methods'] as $m) $scores[$m] = ['1.1.1' => ['v' => $isi]];

            $c = Tpkkp::itemCalc($scores, $item);

            $this->assertSame($label, $c['tingkat'],
                "Diisi {$isi}, tingkat yang tampil seharusnya '{$label}'.");
            $this->assertSame($isi, $c['tingkatNum']);
        }
    }

    /** Dan kategori rasio TIDAK ikut berubah — itu rumus workbook resmi. */
    public function test_kategori_rasio_tetap_rumus_workbook(): void
    {
        $item = ['code' => '1.1.1', 'name' => 'Uji', 'methods' => ['TD', 'FGD', 'KS']];

        $scores = [];
        foreach ($item['methods'] as $m) $scores[$m] = ['1.1.1' => ['v' => 3]];

        $c = Tpkkp::itemCalc($scores, $item);

        $this->assertSame(9.0, (float) $c['nilai']);
        $this->assertSame(15, $c['max']);
        $this->assertEqualsWithDelta(0.6, $c['achv'], 0.0001);
        $this->assertSame(Tpkkp::category(0.6), $c['category'],
            'Kategori harus tetap datang dari ambang rasio, bukan dari tingkat rubrik.');
    }

    /**
     * Pembulatan setengah ke bawah, seragam.
     *
     * 2,5 menjadi 2 dan bukan 3: menaikkan tingkat kematangan yang belum
     * benar-benar dicapai adalah kesalahan yang berpihak pada auditi.
     * Di luar rentang dijepit ke 1..5 supaya tidak ada tingkat keenam.
     */
    public function test_pembulatan_tingkat_setengah_ke_bawah(): void
    {
        foreach ([
            [1.0, 1], [2.4, 2], [2.5, 2], [2.6, 3],
            [3.5, 3], [4.5, 4], [4.6, 5], [5.0, 5],
            [0.2, 1], [6.0, 5],
        ] as [$masuk, $keluar]) {
            $this->assertSame($keluar, Tpkkp::roundLevel($masuk),
                "roundLevel({$masuk}) seharusnya {$keluar}.");
        }

        $this->assertNull(Tpkkp::roundLevel(null));
        $this->assertNull(Tpkkp::levelCategory(null));
    }

    /**
     * Rerata antar-entitas dibulatkan sebelum dipakai.
     *
     * Dua perusahaan bernilai 3 dan 4 memberi rerata 3,5 — tingkat yang
     * tidak ada dalam rubrik mana pun. Dibiarkan desimal, angka itu
     * merambat ke seluruh rekap sebagai nilai yang tidak dapat
     * dijelaskan kepada auditi.
     */
    public function test_rerata_antar_entitas_dibulatkan(): void
    {
        $s = Tpkkp::methodScore(
            ['TD' => ['1.1.1' => ['e' => ['PT A' => 3, 'PT B' => 4]]]],
            'TD', '1.1.1',
        );

        $this->assertSame(3, $s['val'], 'Rerata 3,5 dibulatkan setengah ke bawah menjadi 3.');
        $this->assertEqualsWithDelta(3.5, $s['raw'], 0.0001, 'Rerata mentahnya tetap dibawa.');
        $this->assertSame(2, $s['nEnt']);
    }
}
