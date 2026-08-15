<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Tpkkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * PTPKKP — Matriks, Summary, Hasil (Inertia).
 *
 * Tiga halaman baca-saja yang dipindah bersama. Yang diuji terutama
 * keutuhan datanya: seluruhnya menurunkan angka dari totalCalc() yang
 * sama, jadi satu pemetaan yang salah menyebar ke ketiganya sekaligus.
 */
class TpkkpLanjutInertiaTest extends TestCase
{
    use RefreshDatabase;

    private function masuk(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
    }

    private const HALAMAN = [
        '/tpkkp/matriks' => 'Tpkkp/Matriks',
        '/tpkkp/summary' => 'Tpkkp/Summary',
        '/tpkkp/hasil'   => 'Tpkkp/Hasil',
    ];

    public function test_ketiganya_dirender_inertia(): void
    {
        $this->masuk();

        foreach (self::HALAMAN as $url => $komponen) {
            $this->get($url)->assertOk()->assertInertia(
                fn (AssertableInertia $p) => $p->component($komponen)->has('picker')
            );
        }
    }

    /* ══════════════ matriks ══════════════ */

    public function test_matriks_mengirim_seluruh_item_tanpa_disaring(): void
    {
        // Pencariannya dikerjakan di peramban, jadi server harus mengirim
        // semuanya — kalau tersaring di server, hasil pencarian di klien
        // hanya menyaring sisa yang kebetulan lolos.
        $this->masuk();

        $props = $this->get('/tpkkp/matriks')->assertOk()->viewData('page')['props'];

        $jumlah = 0;
        foreach ($props['indikator'] as $I) {
            foreach ($I['parameter'] as $P) $jumlah += count($P['items']);
        }

        $this->assertSame(Tpkkp::totalItems(), $jumlah,
            'Matriks tidak mengirim seluruh item pengukuran.');
    }

    public function test_matriks_membawa_seluruh_kolom_metode(): void
    {
        $this->masuk();

        $props = $this->get('/tpkkp/matriks')->assertOk()->viewData('page')['props'];

        $this->assertSame(array_keys(Tpkkp::methods()), $props['metode']);
    }

    public function test_matriks_menyembunyikan_kategori_item_yang_belum_lengkap(): void
    {
        // Selama masih ada metode yang belum dinilai, angkanya akan
        // berubah — lencana kategori pada keadaan itu menyesatkan.
        $this->masuk();

        $props = $this->get('/tpkkp/matriks')->assertOk()->viewData('page')['props'];

        $adaBelumLengkap = false;
        foreach ($props['indikator'] as $I) {
            foreach ($I['parameter'] as $P) {
                foreach ($P['items'] as $c) {
                    if ($c['kategori'] === null) $adaBelumLengkap = true;
                }
            }
        }

        $this->assertTrue($adaBelumLengkap,
            'Pada data kosong, seluruh item semestinya belum berkategori.');
    }

    /* ══════════════ summary ══════════════ */

    public function test_summary_menghitung_gap_dan_membedakan_nol_dari_kosong(): void
    {
        $this->masuk();

        $props = $this->get('/tpkkp/summary')->assertOk()->viewData('page')['props'];

        foreach ($props['indikator'] as $I) {
            foreach ($I['parameter'] as $P) {
                if ($P['skor'] === null || $P['target'] === null) {
                    $this->assertNull($P['gap'],
                        "Parameter {$P['kode']}: gap harus null bila capaian atau targetnya belum ada.");
                } else {
                    $this->assertEqualsWithDelta($P['skor'] - $P['target'], $P['gap'], 1e-9);
                }
            }
        }
    }

    public function test_summary_membawa_baris_total(): void
    {
        $this->masuk();

        $this->get('/tpkkp/summary')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->has('total.skor')->has('total.target')->has('total.kategori')->has('total.gap'));
    }

    public function test_summary_lengkap_seluruh_indikator_dan_parameter(): void
    {
        $this->masuk();

        $props = $this->get('/tpkkp/summary')->assertOk()->viewData('page')['props'];

        $this->assertCount(count(Tpkkp::indicators()), $props['indikator']);

        $param = 0;
        foreach ($props['indikator'] as $I) $param += count($I['parameter']);

        $harap = 0;
        foreach (Tpkkp::indicators() as $I) $harap += count($I['params']);

        $this->assertSame($harap, $param);
    }

    /* ══════════════ hasil ══════════════ */

    /**
     * Rentang kategori diturunkan dari ambang, bukan diketik.
     *
     * Versi Blade menuliskannya sebagai teks tetap ("x < 0,5" dan
     * seterusnya). Teks tetap seperti itu diam saja ketika ambangnya
     * berubah, sehingga tabel keterangannya menyebut batas yang tidak
     * lagi dipakai perhitungan di sebelahnya.
     */
    public function test_hasil_menurunkan_rentang_dari_ambang_acuan(): void
    {
        $this->masuk();

        $props = $this->get('/tpkkp/hasil')->assertOk()->viewData('page')['props'];
        $acuan = Tpkkp::ref()['thresholds'];

        $this->assertCount(count($acuan), $props['rentang']);

        foreach ($acuan as $i => $t) {
            $this->assertSame($t['label'], $props['rentang'][$i]['kategori']);
        }

        // Batas pertama harus muncul apa adanya di teksnya.
        $this->assertStringContainsString(
            number_format((float) $acuan[0]['lt'], 1, ',', '.'),
            $props['rentang'][0]['teks']
        );
    }

    public function test_hasil_membawa_seluruh_metode(): void
    {
        $this->masuk();

        $props = $this->get('/tpkkp/hasil')->assertOk()->viewData('page')['props'];

        $this->assertCount(count(Tpkkp::methods()), $props['metode']);

        foreach ($props['metode'] as $m) {
            $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $m['warna']);
        }
    }

    /* ══════════════ bersama ══════════════ */

    public function test_lencana_kategori_dipakai_bersama_bukan_disalin(): void
    {
        // Markup lencana sempat ditulis ulang di tiap halaman; satu
        // komponen membuat perubahan bentuknya cukup sekali.
        foreach (['Matriks', 'Summary', 'Hasil'] as $h) {
            $vue = file_get_contents(resource_path("js/Pages/Tpkkp/{$h}.vue"));

            $this->assertStringContainsString('LencanaKategori', $vue,
                "{$h}.vue tidak memakai komponen lencana bersama.");
        }
    }

    public function test_tamu_tidak_dapat_membuka_ketiganya(): void
    {
        foreach (array_keys(self::HALAMAN) as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }
}
