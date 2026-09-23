<?php

namespace Tests;

use App\Support\Berkas;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

/**
 * Induk seluruh uji.
 *
 * ── JANGAN menjalankan dua proses `php artisan test` sekaligus ──
 *
 * Ujinya berjalan berurutan dalam satu proses, dan itu memang aman.
 * Yang TIDAK aman adalah dua proses phpunit yang hidup bersamaan —
 * misalnya satu suite penuh di latar belakang sementara satu `--filter`
 * dijalankan untuk memeriksa sesuatu.
 *
 * Keduanya memakai satu tempat yang sama: `Storage::fake('local')`
 * selalu berakar di storage/framework/testing/disks/local, apa pun
 * prosesnya, dan tiap pemanggilan MENGOSONGKAN folder itu lebih dulu.
 * Proses kedua karena itu menghapus berkas yang baru saja ditulis proses
 * pertama, di antara `put()` dan permintaannya sendiri.
 *
 * Akibatnya menyesatkan justru karena tampak nyata: BerkasTertutupTest
 * gagal dengan "Unable to retrieve the file_size for file at location:
 * signatures/…" — tepat seperti cacat sungguhan pada penyajian berkas
 * tertutup — lalu hijau kembali saat dijalankan sendirian, sehingga
 * terbaca sebagai uji yang rapuh, bukan sebagai dua proses yang
 * bertabrakan. Basis datanya pun satu dan sama.
 *
 * Jalankan satu per satu. Bila perlu berlatar belakang, tunggu yang
 * sebelumnya selesai.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Kedua disk dipalsukan untuk SETIAP uji, bukan hanya yang ingat.
     *
     * Empat belas berkas uji memuat data contoh — dan data contoh
     * menyalin foto bahaya, logo, dan lampiran ke disk. Tanpa pemalsuan,
     * semuanya ditulis ke storage/app yang SUNGGUHAN, lalu `buang()` pada
     * uji berikutnya menghapus folder yang sama. Pada mesin pengembang
     * itu berarti menjalankan suite menghapus foto data contoh yang
     * sedang dipakai server lokal: halaman Bahaya memuat gambar yang
     * mendadak 404 selama suite berjalan, lalu kembali sesudah perintah
     * `demo:pasang` berikutnya — cacat yang tampak seperti galat
     * penyajian berkas padahal bukan.
     *
     * Uji yang memanggil Storage::fake() sendiri tetap berjalan seperti
     * biasa; pemalsuan kedua hanya mengosongkan disk palsu yang sama.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Berkas::TERTUTUP);
        Storage::fake(Berkas::TERBUKA);
    }

    /**
     * Tegaskan halaman tidak mencetak "NaN" — pada ISINYA, bukan pada
     * kerangkanya.
     *
     * `assertDontSee('NaN')` atas seluruh dokumen bukan uji yang tetap:
     * tiap tanggapan membawa nonce CSP acak 24 huruf (lihat
     * TajukKeamanan), dan sekali waktu nonce itu memuat "NaN" di
     * tengahnya. Ujinya lalu gagal atas halaman yang sama sekali tidak
     * salah, pada perubahan yang sama sekali tidak menyentuhnya —
     * `NMn2FjPtewhNaN1h1RGXD4FZ`, tertangkap persis begitu.
     *
     * Kegagalan seperti itu lebih berbahaya daripada tidak diuji sama
     * sekali: yang menemuinya belajar mengulang perintahnya alih-alih
     * membacanya, dan kegagalan yang sungguhan ikut terulang lewat.
     *
     * Yang dibuang hanya nilai atribut nonce-nya. Isi halaman, termasuk
     * props Inertia tempat NaN benar-benar muncul, diperiksa utuh.
     *
     * "INF" tunduk pada jebakan yang sama, dan lebih sering: tiga huruf
     * lebih mudah muncul kebetulan daripada tiga huruf bercampur besar
     * kecil.
     */
    protected function tanpaNaN(TestResponse $r, array $terlarang = ['NaN', 'INF']): TestResponse
    {
        $isi = preg_replace('/\snonce="[^"]*"/', '', $r->getContent()) ?? '';

        foreach ($terlarang as $kata) {
            $this->assertStringNotContainsString($kata, $isi,
                "Halaman mencetak {$kata} — ada pembagian dengan nol yang lolos ke layar.");
        }

        return $r;
    }
}
