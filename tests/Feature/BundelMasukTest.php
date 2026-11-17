<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Penjagaan atas berat bundel yang diunduh SEBELUM layar pertama muncul.
 *
 * Yang dijaga di sini punya satu sifat bersama: melanggarnya tidak
 * menimbulkan galat apa pun. Halamannya tetap benar, ujinya tetap hijau,
 * dan `npm run build` tetap berhasil — yang berubah hanya berapa lama
 * seseorang menatap layar kosong. Di kantor dengan serat optik selisih
 * itu tak terasa; di site tambang, di ujung sambungan yang lambat dan
 * sering terputus, itulah selisih antara aplikasi yang terasa hidup dan
 * aplikasi yang disangka rusak lalu dimuat ulang berkali-kali.
 *
 * Karena itu yang diuji bentuk kodenya, bukan hasil buildnya: uji yang
 * menuntut `npm run build` akan dilewati pada mesin tanpa Node, dan
 * penjagaan yang dilewati bukan penjagaan.
 */
class BundelMasukTest extends TestCase
{
    /**
     * Chart.js tidak boleh kembali ke bundel masuk.
     *
     * Dua dari 147 halaman memakainya. Ketika `inertia.ts` mengimpornya
     * secara statis, 145 halaman sisanya ikut mengunduh 68 kB terkempa
     * yang tidak pernah mereka sentuh — separuh dari seluruh bundel
     * masuknya (137,8 kB -> 69,4 kB setelah dipisah).
     *
     * Impor dinamis yang menggantikannya tidak menuntut satu baris pun
     * berubah di kedua halaman itu: `eqChartSiap` memang sudah berbentuk
     * callback sejak ia harus menunggu skrip dari CDN.
     */
    public function test_chartjs_tidak_ikut_bundel_masuk(): void
    {
        $inertia = file_get_contents(resource_path('js/inertia.ts'));

        $this->assertDoesNotMatchRegularExpression(
            "#^\s*import\s+[^\n]*['\"]\./bagan['\"]#m",
            $inertia,
            'inertia.ts mengimpor ./bagan secara statis; Chart.js kembali masuk ke '
            . 'bundel yang diunduh setiap halaman. Pakai import() dinamis.',
        );

        /* Di dalam badan `eqChartSiap` saja: anotasi tipenya sendiri
           memuat `import('./bagan')`, jadi pencarian seluruh berkas tetap
           hijau meski pemuatnya dicabut. */
        $badan = substr($inertia, (int) strpos($inertia, 'window.eqChartSiap'));

        $this->assertStringContainsString(
            "import('./bagan')",
            $badan,
            'Pemuat grafik tertunda hilang dari inertia.ts; kedua halaman bergrafik '
            . 'tidak akan pernah mendapat window.Chart.',
        );
    }

    /**
     * Halaman dimuat satu per satu, bukan seluruhnya di muka.
     *
     * `import.meta.glob(..., { eager: true })` menarik ke-147 halaman ke
     * dalam satu berkas 1,5 MB. Seorang pengawas yang membuka satu
     * halaman laporan bahaya ikut mengunduh modul peledakan, konservasi,
     * penirisan, gudang, dan seratus empat puluh dua halaman lain yang
     * tidak akan ia buka hari itu.
     */
    public function test_halaman_dimuat_saat_dibutuhkan(): void
    {
        $inertia = file_get_contents(resource_path('js/inertia.ts'));

        $this->assertDoesNotMatchRegularExpression(
            '/import\.meta\.glob[^(]*\([^)]*eager/s',
            $inertia,
            'Glob halaman kembali memakai `eager`; seluruh 147 halaman menyatu '
            . 'menjadi satu unduhan di muka.',
        );
    }

    /**
     * Titik masuk yang dibangun harus ada pemuatnya.
     *
     * `app.js` sempat terus dibangun 47 kB tiap kali setelah halaman
     * Blade terakhir pindah ke Vue — tidak ada satu pun halaman yang
     * menariknya, dan tidak ada satu pun yang memberi tahu. Uji ini
     * menahan hal yang sama terjadi lagi pada titik masuk berikutnya.
     */
    public function test_setiap_titik_masuk_ada_yang_memuat(): void
    {
        $vite = file_get_contents(base_path('vite.config.js'));

        preg_match('/input:\s*\[(.*?)\]/s', $vite, $m);
        $this->assertNotEmpty($m, 'Daftar input Vite tidak terbaca dari vite.config.js.');

        preg_match_all("#['\"](resources/[^'\"]+)['\"]#", $m[1], $titik);

        $blade = '';
        foreach (glob(resource_path('views/*.blade.php')) as $b) {
            $blade .= file_get_contents($b);
        }

        foreach ($titik[1] as $t) {
            $this->assertStringContainsString(
                $t,
                $blade,
                "{$t} dibangun tiap kali tetapi tidak ada @vite yang memuatnya.",
            );
        }
    }
}
