<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
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
