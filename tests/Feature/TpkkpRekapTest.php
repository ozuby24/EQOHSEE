<?php

namespace Tests\Feature;

use App\Models\{TpkkpAssessment, User};
use App\Support\Tpkkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * PTPKKP — Rekapitulasi (Inertia).
 *
 * Halaman kedua yang dipindah ke Vue, sengaja terhubung dengan Formulir
 * Nilai. Yang paling penting diuji di sini bukan tampilannya, melainkan
 * bahwa memilih perusahaan tidak memaksa tabel utama ikut dihitung ulang
 * — itulah satu-satunya alasan halaman ini memakai reload sebagian,
 * bukan navigasi biasa.
 */
class TpkkpRekapTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['is_admin' => true]);
        $this->actingAs($u);

        return $u;
    }

    public function test_halaman_dirender_inertia(): void
    {
        $this->admin();

        $this->get('/tpkkp/rekap')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Tpkkp/Rekap'));
    }

    public function test_prop_halaman_lengkap(): void
    {
        $this->admin();

        $this->get('/tpkkp/rekap')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('judul')->has('subjudul')->has('tahun')
                ->has('hasil.skor')->has('hasil.target')->has('hasil.indikator')
                ->has('perusahaan')
                ->has('metodePerusahaan')
                ->has('ambang')
                ->where('entitasAktif', null)
                ->where('rincian', null)
                ->where('lemah', null)
            );
    }

    public function test_jumlah_indikator_sama_dengan_acuan(): void
    {
        $this->admin();

        $props = $this->get('/tpkkp/rekap')->assertOk()->viewData('page')['props'];

        $this->assertCount(count(Tpkkp::indicators()), $props['hasil']['indikator']);
    }

    public function test_memilih_perusahaan_yang_tidak_dikenal_diabaikan(): void
    {
        $this->admin();

        $this->get('/tpkkp/rekap?entitas=Perusahaan-Tidak-Ada')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('entitasAktif', null)
                ->where('rincian', null)
            );
    }

    public function test_memilih_perusahaan_yang_valid_mengisi_rincian(): void
    {
        $this->admin();

        $a = TpkkpAssessment::forYear((int) now()->year);
        $perusahaan = $a->entitiesOf('TD');

        if (empty($perusahaan)) {
            $this->markTestSkipped('Acuan tidak membawa daftar entitas TD.');
        }

        $co = $perusahaan[0];

        $this->get('/tpkkp/rekap?entitas='.urlencode($co))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('entitasAktif', $co)
                ->has('rincian')
                ->has('lemah')
            );
    }

    /**
     * Reload sebagian tidak boleh menghitung ulang tabel utama.
     *
     * `hasil` dibungkus closure dengan totalCalc() DI DALAM closure-nya,
     * persis supaya Inertia dapat melewatinya saat reload sebagian hanya
     * meminta `rincian`/`lemah`/`entitasAktif`. Kalau totalCalc() dipanggil
     * sebelum closure-nya dibuat, pembungkusan itu cuma ilusi — penghitungan
     * sesungguhnya sudah terjadi sebelum Inertia sempat memutuskan prop
     * mana yang diperlukan.
     */
    public function test_reload_sebagian_tidak_menyertakan_tabel_utama(): void
    {
        $u = $this->admin();

        $a = TpkkpAssessment::forYear((int) now()->year);
        $perusahaan = $a->entitiesOf('TD');
        if (empty($perusahaan)) $this->markTestSkipped('Acuan tidak membawa daftar entitas TD.');

        // Reload sebagian mensyaratkan X-Inertia-Version yang cocok dengan
        // versi aset di server — tanpa itu Inertia menolaknya dengan 409
        // supaya klien memuat ulang penuh. Versinya diambil dari kunjungan
        // biasa (HTML utuh, bukan XHR) lebih dulu, dari atribut data-page.
        $html = $this->get('/tpkkp/rekap')->getContent();
        preg_match('#<script data-page="app" type="application/json">(.+?)</script>#s', $html, $m);
        $isi   = json_decode($m[1] ?? '', true);
        $versi = $isi['version'] ?? null;

        $resp = $this->withHeaders([
            'X-Inertia'                   => 'true',
            'X-Inertia-Version'           => $versi,
            'X-Inertia-Partial-Data'      => 'rincian,lemah,entitasAktif',
            'X-Inertia-Partial-Component' => 'Tpkkp/Rekap',
        ])->get('/tpkkp/rekap?entitas='.urlencode($perusahaan[0]));

        $resp->assertOk();
        $data = json_decode($resp->getContent(), true);

        $this->assertArrayHasKey('rincian', $data['props']);
        $this->assertArrayNotHasKey('hasil', $data['props'],
            'Reload sebagian mengirim ulang tabel utama yang tidak diminta.');
        $this->assertArrayNotHasKey('perusahaan', $data['props']);
    }

    public function test_tautan_kembali_ke_formulir_nilai_memakai_link_inertia(): void
    {
        // <a href> biasa selalu memicu navigasi peramban penuh; komponen
        // <Link> Inertia diperlukan supaya perpindahan antar dua halaman
        // Inertia ini instan.
        $vue = file_get_contents(resource_path('js/Pages/Tpkkp/Rekap.vue'));

        $this->assertStringContainsString("Link href=\"/tpkkp/penilaian\"", $vue);
    }

    /**
     * Bilah samping memilih bentuk tautan menurut tujuannya.
     *
     * Uji ini sempat menuntut SELURUH menu memakai <Link>, dengan alasan
     * <Link> akan jatuh sendiri ke navigasi penuh untuk halaman Blade.
     * Dugaan itu keliru: <Link> ke halaman Blade tidak berpindah sama
     * sekali. Yang benar adalah memilih bentuknya menurut tanda dari
     * server, dan itulah yang dijaga di sini.
     */
    public function test_sidebar_vue_memilih_bentuk_tautan_menurut_tujuannya(): void
    {
        $vue = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));

        $this->assertStringContainsString("inertia ? Link : 'a'", $vue,
            'Bilah samping tidak membedakan tujuan Inertia dan Blade.');

        $this->assertMatchesRegularExpression('/<component\s+:is="tautan\(m\.inertia\)"/', $vue,
            'Pemilih modul harus memilih bentuk tautan dari tanda inertia.');
        $this->assertMatchesRegularExpression('/<component\s+:is="tautan\(b\.inertia\)"/', $vue,
            'Butir menu harus memilih bentuk tautan dari tanda inertia.');
    }


    /* ══════════════ navigasi dalam-halaman ══════════════ */

    /**
     * Halaman Vue wajib membawa navigasi PTPKKP.
     *
     * Saat Penilaian dan Rekapitulasi dipindah ke Inertia, keduanya
     * terkirim TANPA deretan chip navigasi — larik tab itu tertulis di
     * dalam _picker.blade.php dan tidak dapat dijangkau dari luar view.
     * Akibatnya satu-satunya jalan keluar dari halaman Penilaian adalah
     * tombol back peramban. Tidak ada galat apa pun; navigasinya sekadar
     * hilang.
     */
    public function test_kedua_halaman_vue_membawa_navigasi_picker(): void
    {
        $this->admin();

        foreach (['/tpkkp/penilaian', '/tpkkp/rekap'] as $url) {
            $props = $this->get($url)->assertOk()->viewData('page')['props'];

            $this->assertArrayHasKey('picker', $props, "{$url} terkirim tanpa navigasi.");
            $this->assertCount(count(\App\Support\TpkkpNav::TABS), $props['picker']['tabs'],
                "{$url} membawa jumlah tab yang berbeda dari katalog.");
        }
    }

    public function test_tab_yang_sedang_dibuka_ditandai_aktif(): void
    {
        $this->admin();

        $props = $this->get('/tpkkp/rekap')->assertOk()->viewData('page')['props'];
        $aktif = collect($props['picker']['tabs'])->firstWhere('aktif', true);

        $this->assertNotNull($aktif, 'Tidak ada tab yang ditandai aktif.');
        $this->assertSame('Rekapitulasi', $aktif['label']);
    }

    public function test_tiap_tab_punya_ikon_dan_alamat(): void
    {
        $this->admin();

        $props = $this->get('/tpkkp/penilaian')->assertOk()->viewData('page')['props'];

        foreach ($props['picker']['tabs'] as $tab) {
            $this->assertNotEmpty($tab['ikon'], "Tab '{$tab['label']}' tanpa ikon.");
            $this->assertNotEmpty($tab['url'],  "Tab '{$tab['label']}' tanpa alamat.");
        }
    }

    /**
     * Blade dan Vue harus membaca daftar tab yang sama.
     *
     * Dua salinan daftar navigasi akan berbeda diam-diam setiap kali ada
     * halaman baru — dan yang tertinggal tidak menimbulkan galat, hanya
     * satu tampilan yang kehilangan satu menu.
     */
    public function test_picker_blade_membaca_katalog_yang_sama(): void
    {
        $blade = file_get_contents(resource_path('views/tpkkp/_picker.blade.php'));

        $this->assertStringContainsString('TpkkpNav::TABS', $blade,
            '_picker.blade.php masih memakai daftar tab sendiri.');
    }

    public function test_seluruh_rute_tab_benar_terdaftar(): void
    {
        // Tab yang menunjuk rute tak terdaftar akan melempar saat route()
        // dipanggil — dan itu meledakkan seluruh halaman, bukan cuma tabnya.
        foreach (\App\Support\TpkkpNav::TABS as [$rute, $label, $ikon]) {
            $this->assertTrue(\Illuminate\Support\Facades\Route::has($rute),
                "Tab '{$label}' menunjuk rute '{$rute}' yang tidak terdaftar.");
            $this->assertArrayHasKey($ikon, \App\Support\TpkkpNav::IKON,
                "Tab '{$label}' memakai ikon '{$ikon}' yang tidak ada.");
        }
    }

    public function test_pengguna_biasa_dapat_membuka_rekap(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/tpkkp/rekap')->assertOk();
    }

    public function test_tamu_tidak_dapat_membuka_rekap(): void
    {
        $this->get('/tpkkp/rekap')->assertRedirect(route('login'));
    }
}
