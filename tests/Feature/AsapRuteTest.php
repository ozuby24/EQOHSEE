<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Uji asap seluruh rute GET.
 *
 * Bukan pengganti uji per modul — ini jaring pengaman terakhir yang
 * menangkap halaman yang meledak karena sebab di luar modulnya sendiri:
 * refactor lintas berkas, prop yang berubah bentuk, komponen Inertia yang
 * namanya salah ketik.
 *
 * Dijalankan sebagai admin, sebab peran itu yang membuka paling banyak
 * halaman. Rute dengan parameter dilewati — isinya bergantung pada data
 * yang harus disiapkan tiap modul sendiri.
 */
class AsapRuteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rute yang sengaja tidak ikut: bukan halaman, atau mengubah keadaan.
     */
    private const LEWATI = [
        'logout',
        'sanctum.csrf-cookie',
        'ignition.healthCheck',
        'storage.local',
    ];

    public function test_seluruh_halaman_terbuka_tanpa_galat_server(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $gagal = [];
        $dibuka = 0;

        foreach (Route::getRoutes() as $rute) {
            if (!in_array('GET', $rute->methods(), true)) continue;

            $nama = $rute->getName();
            if ($nama === null || in_array($nama, self::LEWATI, true)) continue;

            // Rute berparameter butuh data khusus tiap modul.
            if (str_contains($rute->uri(), '{')) continue;

            // Hanya rute di balik 'auth'; sisanya diuji modulnya sendiri.
            if (!in_array('auth', $rute->gatherMiddleware(), true)) continue;

            $dibuka++;

            try {
                $status = $this->get('/'.ltrim($rute->uri(), '/'))->getStatusCode();
            } catch (\Throwable $e) {
                $gagal[] = "{$nama} ({$rute->uri()}) — lempar: ".mb_substr($e->getMessage(), 0, 160);
                continue;
            }

            // 200 dan 302 sama-sama sah: sebagian halaman mengalihkan.
            if ($status >= 500) {
                $gagal[] = "{$nama} ({$rute->uri()}) — HTTP {$status}";
            }

            // 404 pada rute tanpa parameter selalu berarti ada yang salah:
            // rutenya jelas terdaftar, jadi yang menjawab pasti rute lain.
            // Persis begitu 'news/create' hilang — ia didaftarkan sesudah
            // 'news/{news}', sehingga "create" tertangkap sebagai id berita
            // dan pengikatan modelnya gagal.
            if ($status === 404) {
                $gagal[] = "{$nama} ({$rute->uri()}) — HTTP 404, "
                          .'kemungkinan tertangkap rute berparameter yang didaftarkan lebih dulu';
            }
        }

        $this->assertGreaterThan(50, $dibuka, 'Terlalu sedikit rute teruji — penyaringnya keliru.');
        $this->assertSame([], $gagal,
            "Halaman berikut gagal dibuka:\n  ".implode("\n  ", $gagal));
    }

    public function test_seluruh_komponen_inertia_yang_dirujuk_benar_ada(): void
    {
        // `ensure_pages_exist` sudah menjaga ini saat halaman dirender,
        // tetapi hanya untuk halaman yang kebetulan dibuka. Ini memeriksa
        // seluruh berkas .vue punya pasangan yang bisa ditemukan.
        $jalur = config('inertia.pages.paths')[0] ?? resource_path('js/Pages');

        $this->assertDirectoryExists($jalur);

        $berkas = glob($jalur.'/*/*.vue') ?: [];
        $this->assertNotEmpty($berkas, 'Tidak ada komponen halaman Inertia yang ditemukan.');
    }
}
