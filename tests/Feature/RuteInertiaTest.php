<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\RuteInertia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Daftar rute Inertia harus cocok dengan kenyataan.
 *
 * Sisi Vue memilih bentuk tautan dari daftar ini: <Link> untuk halaman
 * Inertia, <a> biasa untuk Blade. Salah menandai bukan sekadar membuat
 * perpindahan lebih lambat — <Link> ke halaman Blade TIDAK berpindah sama
 * sekali. Ia mengirim permintaan ber-header X-Inertia, menerima HTML
 * utuh, lalu menampilkan modal galat dan diam di tempat.
 *
 * Sempat terjadi persis begitu: seluruh bilah samping memakai <Link>,
 * dan dari halaman Vue tidak satu pun menu bisa diklik.
 *
 * Karena itu daftar ini tidak dijaga oleh disiplin melainkan oleh uji:
 * tiap rute benar-benar dipanggil, lalu jenis tanggapannya dibandingkan
 * dengan daftar. Yang tertinggal maupun yang kelebihan sama-sama ketahuan.
 */
class RuteInertiaTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    /** Apakah tanggapan sebuah alamat benar-benar Inertia? */
    private function inertia(string $url): bool
    {
        $html = $this->get($url)->getContent();

        return str_contains($html, '<script data-page="app"');
    }

    public function test_setiap_rute_terdaftar_memang_dirender_inertia(): void
    {
        $this->masuk();

        foreach (RuteInertia::NAMA as $nama) {
            $this->assertTrue(Route::has($nama), "Rute '{$nama}' tidak terdaftar.");

            $this->assertTrue($this->inertia(route($nama, [], false)),
                "Rute '{$nama}' terdaftar sebagai Inertia tetapi mengirim HTML Blade. ".
                'Tautan <Link> ke sana tidak akan berpindah sama sekali.');
        }
    }

    public function test_tidak_ada_halaman_inertia_yang_lupa_didaftarkan(): void
    {
        $this->masuk();

        $tertinggal = [];

        foreach (Route::getRoutes() as $rute) {
            if (!in_array('GET', $rute->methods(), true)) continue;
            if (str_contains($rute->uri(), '{')) continue;

            $nama = $rute->getName();
            if ($nama === null || RuteInertia::ada($nama)) continue;
            if (!in_array('auth', $rute->gatherMiddleware(), true)) continue;

            if ($this->inertia('/'.ltrim($rute->uri(), '/'))) {
                $tertinggal[] = $nama;
            }
        }

        // Halaman Inertia yang tidak terdaftar tetap dapat dibuka, tetapi
        // tautan ke sana digambar sebagai <a> biasa — berpindah dengan
        // memuat ulang penuh, kehilangan seluruh manfaat pemindahannya.
        $this->assertSame([], $tertinggal,
            "Halaman berikut dirender Inertia tetapi belum masuk RuteInertia::NAMA:\n  ".
            implode("\n  ", $tertinggal));
    }

    public function test_menu_bersama_menandai_tujuan_inertia(): void
    {
        $this->masuk();

        $props = $this->get('/tpkkp/penilaian')->assertOk()->viewData('page')['props'];

        foreach ($props['menu']['modul'] as $m) {
            $this->assertArrayHasKey('inertia', $m, "Modul '{$m['label']}' tanpa tanda inertia.");
        }

        foreach ($props['menu']['grup'] as $g) {
            foreach ($g['butir'] as $b) {
                $this->assertArrayHasKey('inertia', $b, "Menu '{$b['label']}' tanpa tanda inertia.");
            }
        }

        // Di modul PTPKKP, tepat dua butir menu menuju halaman Inertia.
        $inertia = collect($props['menu']['grup'])
            ->flatMap(fn ($g) => $g['butir'])
            ->where('inertia', true)
            ->pluck('label')
            ->sort()->values()->all();

        $this->assertSame(['Formulir Nilai', 'Rekapitulasi'], $inertia);
    }

    public function test_picker_menandai_tab_inertia(): void
    {
        $this->masuk();

        $props = $this->get('/tpkkp/rekap')->assertOk()->viewData('page')['props'];

        $inertia = collect($props['picker']['tabs'])->where('inertia', true)
            ->pluck('label')->sort()->values()->all();

        $this->assertSame(['Penilaian', 'Rekapitulasi'], $inertia);

        // Sisanya harus ditandai Blade, bukan dibiarkan tanpa tanda.
        foreach ($props['picker']['tabs'] as $t) {
            $this->assertIsBool($t['inertia'], "Tab '{$t['label']}' tanpa tanda inertia.");
        }
    }

    public function test_komponen_vue_memilih_bentuk_tautan_dari_tanda_itu(): void
    {
        foreach (['js/Layouts/AppLayout.vue', 'js/Components/PickerTpkkp.vue'] as $berkas) {
            $vue = file_get_contents(resource_path($berkas));

            $this->assertStringContainsString('tautan(', $vue,
                "{$berkas} tidak memilih bentuk tautan dari tanda inertia.");
            $this->assertStringContainsString("inertia ? Link : 'a'", $vue,
                "{$berkas} tidak membedakan <Link> dan <a>.");
        }
    }
}
