<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Prop halaman tidak boleh menimpa prop bersama.
 *
 * HandleInertiaRequests membagikan sejumlah prop ke SETIAP halaman:
 * pengguna yang sedang masuk, menu, pesan kilat, tema. Inertia
 * menggabungkan prop halaman di atasnya, jadi halaman yang memakai nama
 * yang sama diam-diam mengganti isinya.
 *
 * Akibatnya tidak berupa galat yang jelas. Tata letak memanggil
 * pengguna.nama atas nilai yang ternyata larik, render Vue melempar, dan
 * yang terlihat pengguna hanyalah halaman kosong — tanpa pesan, tanpa
 * petunjuk halaman mana yang salah. Sempat terjadi persis begitu pada
 * daftar pengguna admin.
 */
class PropInertiaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Nama yang sudah dipakai bersama dan karena itu terlarang bagi
     * prop halaman. Sengaja ditulis ulang di sini, bukan dibaca dari
     * middleware-nya: daftar ini adalah pernyataan tentang apa yang
     * boleh berubah, dan membacanya dari sumber yang sama membuat uji
     * ini ikut berubah bersama kesalahannya.
     */
    private const MILIK_BERSAMA = [
        'pengguna', 'menu', 'kilat', 'pengumuman', 'tema', 'warna', 'errors',
    ];

    public function test_tidak_ada_halaman_yang_menimpa_prop_bersama(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $bentrok = [];
        $diperiksa = 0;

        foreach (Route::getRoutes() as $rute) {
            $nama = $rute->getName();

            if ($nama === null || !in_array('GET', $rute->methods(), true)) continue;
            if (str_contains($rute->uri(), '{')) continue;
            if (!in_array('auth', $rute->gatherMiddleware(), true)) continue;

            $tanggapan = $this->get('/'.ltrim($rute->uri(), '/'));
            if ($tanggapan->getStatusCode() !== 200) continue;

            // Halaman Blade tidak punya 'page' sama sekali, dan sebagian
            // rute mengalirkan berkas — StreamedResponse bahkan tidak
            // punya properti ->original. Keduanya disaring lebih dulu.
            $dasar = $tanggapan->baseResponse;
            if (!$dasar instanceof \Illuminate\Http\Response) continue;

            $asli = $dasar->original;
            if (!$asli instanceof \Illuminate\Contracts\View\View) continue;

            $halaman = $asli->getData()['page'] ?? null;
            if (!is_array($halaman) || !isset($halaman['props'])) continue;  // bukan Inertia

            $diperiksa++;

            // Prop bersama tetap ada di tiap halaman; yang dicari adalah
            // yang isinya sudah bukan miliknya lagi.
            $p = $halaman['props'];

            if (isset($p['pengguna']) && !array_key_exists('nama', (array) $p['pengguna'])) {
                $bentrok[] = "{$nama} — 'pengguna' bukan lagi pengguna yang sedang masuk";
            }
            if (isset($p['menu']) && !is_array($p['menu'])) {
                $bentrok[] = "{$nama} — 'menu' bukan lagi menu bilah samping";
            }
            if (isset($p['kilat']) && !array_key_exists('sukses', (array) $p['kilat'])) {
                $bentrok[] = "{$nama} — 'kilat' bukan lagi pesan kilat";
            }
            if (isset($p['warna']) && !array_key_exists('aksen', (array) $p['warna'])) {
                $bentrok[] = "{$nama} — 'warna' bukan lagi warna tema";
            }
        }

        $this->assertGreaterThan(20, $diperiksa, 'Terlalu sedikit halaman Inertia teruji — penyaringnya keliru.');
        $this->assertSame([], $bentrok,
            "Prop halaman menimpa prop bersama:\n  ".implode("\n  ", $bentrok)
            ."\n\nNama yang sudah dipakai bersama: ".implode(', ', self::MILIK_BERSAMA));
    }
}
