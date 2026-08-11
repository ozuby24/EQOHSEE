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
        // Pemuat dan tema Chart.js berasal dari partial Blade yang sama
        // dengan halaman lain; menyalinnya ke Vue berarti dua setelan warna
        // dan font yang akan berbeda cepat atau lambat.
        $vue  = file_get_contents(resource_path('js/Pages/Tpkkp/Beranda.vue'));
        $akar = file_get_contents(resource_path('views/app-inertia.blade.php'));

        $this->assertStringContainsString("@include('tpkkp._chart')", $akar,
            'Root Inertia tidak memuat pemuat Chart.js bersama.');
        $this->assertStringNotContainsString('Chart.defaults', $vue,
            'Setelan tema Chart.js tergandakan di komponen Vue.');
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
