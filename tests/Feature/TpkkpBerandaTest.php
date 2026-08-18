<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Tpkkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * PTPKKP — Beranda (Inertia).
 *
 * Halaman ketiga yang dipindah, dan yang pertama membawa grafik. Grafik
 * memunculkan risiko yang tidak ada pada halaman sebelumnya: pada
 * navigasi Inertia kanvasnya diganti tanpa halaman dimuat ulang, jadi
 * instance Chart lama harus dibuang komponennya sendiri.
 */
class TpkkpBerandaTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    public function test_halaman_dirender_inertia(): void
    {
        $this->masuk();

        $this->get('/tpkkp')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Tpkkp/Beranda'));
    }

    public function test_prop_halaman_lengkap(): void
    {
        $this->masuk();

        $this->get('/tpkkp')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('judul')->has('subjudul')->has('picker')
                ->has('identitas.organisasi')->has('identitas.tahun')
                ->has('hasil.skor')->has('hasil.target')->has('hasil.indikator')
                ->has('metode')->has('tingkat')->has('gaps')
                ->has('radar.label')->has('radar.capaian')->has('radar.target')
                ->has('totalItem')
            );
    }

    public function test_data_radar_sejajar_dengan_indikator(): void
    {
        // Tiga larik radar harus sepanjang daftar indikator dan berurutan
        // sama; kalau tidak, kurvanya menggambarkan indikator yang keliru
        // tanpa satu pun galat muncul.
        $this->masuk();

        $props = $this->get('/tpkkp')->assertOk()->viewData('page')['props'];
        $n = count($props['hasil']['indikator']);

        $this->assertSame($n, count($props['radar']['label']));
        $this->assertSame($n, count($props['radar']['capaian']));
        $this->assertSame($n, count($props['radar']['target']));

        foreach ($props['hasil']['indikator'] as $i => $ind) {
            $this->assertStringContainsString($ind['kode'], $props['radar']['label'][$i]);
        }
    }

    public function test_sebaran_tingkat_lengkap_lima_dan_berwarna(): void
    {
        $this->masuk();

        $props = $this->get('/tpkkp')->assertOk()->viewData('page')['props'];

        $this->assertCount(count(Tpkkp::LV), $props['tingkat']);

        foreach ($props['tingkat'] as $i => $t) {
            $this->assertSame(Tpkkp::LV[$i], $t['nama']);
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $t['warna']);
        }
    }

    public function test_metode_membawa_seluruh_metode_acuan(): void
    {
        $this->masuk();

        $props = $this->get('/tpkkp')->assertOk()->viewData('page')['props'];

        $this->assertCount(count(Tpkkp::methods()), $props['metode']);
    }

    /**
     * Instance Chart harus dibuang saat komponen dilepas.
     *
     * Tanpa itu, tiap kali halaman ini dibuka lewat navigasi Inertia satu
     * instance baru dibuat sementara yang lama tetap memegang kanvas yang
     * sudah lepas dari dokumen. Tidak ada galat; pemakaian memori hanya
     * naik terus selama sesi berjalan.
     */
    public function test_grafik_dibersihkan_saat_komponen_dilepas(): void
    {
        $vue = file_get_contents(resource_path('js/Pages/Tpkkp/Beranda.vue'));

        $this->assertStringContainsString('onBeforeUnmount', $vue,
            'Beranda.vue tidak membuang instance Chart saat dilepas.');
        $this->assertStringContainsString('destroy()', $vue);
    }

    public function test_setelan_grafik_tidak_digandakan_di_sisi_vue(): void
    {
        /* Setelan warna dan font Chart.js harus tinggal di SATU tempat.
           Dua salinan akan berbeda isinya cepat atau lambat, dan bedanya
           baru ketahuan saat dua grafik dibandingkan berdampingan.

           Tempatnya kini resources/js/bagan.ts, bukan lagi partial Blade
           tpkkp._chart. Yang diuji tetap aturannya — satu tempat — bukan
           tempat mana; menegaskan nama berkasnya akan membuat uji ini
           gagal setiap kali pemuatnya dipindah, padahal aturannya utuh. */
        $vue   = file_get_contents(resource_path('js/Pages/Tpkkp/Beranda.vue'));
        $bagan = file_get_contents(resource_path('js/bagan.ts'));

        $this->assertStringContainsString('Chart.defaults', $bagan,
            'Setelan tema Chart.js tidak ada di pemuat bersama.');
        $this->assertStringNotContainsString('Chart.defaults', $vue,
            'Setelan tema Chart.js tergandakan di komponen Vue.');
    }

    /**
     * Chart.js dibundel, tidak ditarik dari jaringan luar.
     *
     * Bukan hanya soal keamanan pasokan dan Content-Security-Policy. Di
     * jaringan tambang yang tertutup — tempat aplikasi ini justru dipakai
     * — skrip dari CDN gagal dimuat dan grafiknya kosong tanpa satu pun
     * penjelasan. Pages/Admin/Sistem.vue sudah pernah ditulis ulang
     * menjadi SVG karena persis itu, dan alasannya tercatat di sana.
     */
    public function test_chart_js_tidak_datang_dari_cdn(): void
    {
        /* Ditegaskan pada HALAMAN YANG TERGAMBAR, bukan pada berkas
           sumbernya. Berkas sumber memuat komentar yang menyebut alamat
           CDN lamanya — penjelasan mengapa ia dibuang — dan uji yang
           mencari teksnya di sumber akan tersandung pada penjelasan itu
           sendiri. Yang penting bukan kata apa yang tertulis di berkas
           melainkan apa yang benar-benar dikirim ke peramban. */
        $this->masuk();
        $isi = $this->get('/tpkkp')->assertOk()->getContent();

        $this->assertStringNotContainsString('cdn.jsdelivr.net', $isi);
        $this->assertStringNotContainsString('unpkg.com', $isi);

        $masuk = file_get_contents(resource_path('js/inertia.ts'));
        $this->assertStringContainsString("import './bagan'", $masuk,
            'Pemuat grafik tidak ikut dibundel; halaman bergrafik akan kosong.');
    }

    public function test_tamu_tidak_dapat_membuka_beranda(): void
    {
        $this->get('/tpkkp')->assertRedirect(route('login'));
    }

    public function test_pengguna_biasa_dapat_membuka_beranda(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/tpkkp')->assertOk();
    }
}
