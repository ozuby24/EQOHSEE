<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tajuk keamanan pada tanggapan.
 *
 * Sebagian tajuk ini juga dipasang nginx. Yang ada di sini tetap
 * diperlukan karena nginx bukan satu-satunya jalan menuju aplikasi:
 * `php artisan serve` saat menyiapkan data, penerusan porta lewat SSH
 * ketika memeriksa masalah, dan berkas konfigurasi nginx yang
 * tertimpa pembaruan paket — ketiganya menghasilkan aplikasi yang
 * berjalan tanpa satu pun tajuk itu, dan tak satu pun dari ketiganya
 * menimbulkan tanda bahwa perlindungannya hilang.
 *
 * KUNCI: tajuk yang sudah ada TIDAK ditimpa. `add_header` pada nginx
 * menambahkan, bukan mengganti; bila keduanya memasang nilai yang
 * berbeda, peramban menerima dua tajuk dengan nama sama dan sebagian
 * memilih menolak keduanya. Karena itu di sini dipakai `->has()` lebih
 * dulu, dan nginx tetap menjadi pemilik keputusan bila ia memang
 * memasangnya.
 *
 * HSTS sengaja tidak dinyalakan secara bawaan, meneruskan keputusan
 * yang sudah tertulis pada berkas nginx: pemasangan ini pernah
 * kehilangan blok 443-nya satu kali, dan HSTS membuat peramban menolak
 * kembali ke http selama masa berlakunya — situs yang kehilangan
 * https-nya menjadi tidak dapat dibuka sama sekali, bukan sekadar
 * tidak terenkripsi. Dinyalakan lewat KEAMANAN_HSTS=true setelah
 * beberapa deploy membuktikan https-nya bertahan.
 */
class TajukKeamanan
{
    /**
     * Tajuk yang selalu masuk akal, berapa pun bentuk tanggapannya.
     *
     * @var array<string,string>
     */
    private const TAJUK = [
        /* Peramban dilarang menebak jenis berkas. Tanpa ini, berkas
           unggahan yang isinya HTML dapat dijalankan sebagai halaman
           meski dikirim sebagai gambar. */
        'X-Content-Type-Options' => 'nosniff',

        /* Halaman tidak boleh dibingkai situs lain. Membingkai halaman
           persetujuan lalu menutupinya dengan tombol lain adalah cara
           paling murah membuat orang menyetujui sesuatu yang tidak
           dilihatnya. */
        'X-Frame-Options' => 'SAMEORIGIN',

        /* Alamat halaman tidak ikut keluar ke situs lain. Tautan dari
           halaman modul memuat nomor dokumen dan kadang nomor izin
           kerja pada alamatnya; tanpa ini, alamat itu terkirim ke tiap
           situs yang ditautkan. */
        'Referrer-Policy' => 'strict-origin-when-cross-origin',

        /* Perangkat keras dimatikan seluruhnya. Aplikasi ini tidak
           meminta kamera, mikrofon, maupun lokasi di mana pun; menutup
           pintu yang tidak dipakai lebih murah daripada mengawasinya. */
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $tanggapan = $next($request);

        foreach (self::TAJUK as $nama => $nilai) {
            if (!$tanggapan->headers->has($nama)) {
                $tanggapan->headers->set($nama, $nilai);
            }
        }

        /* Halaman aplikasi tidak boleh disimpan peramban maupun proksi
           di antaranya. Yang dilindungi bukan kerahasiaan biasa: pada
           komputer bersama di kantor site, tombol "kembali" sesudah
           orang keluar akan menampilkan kembali halaman terakhirnya
           dari simpanan peramban — lengkap dengan datanya — tanpa
           pernah menyentuh server.

           Satu-satunya tajuk di berkas ini yang DITIMPA, bukan
           dilewati bila sudah ada. Alasannya: yang sudah ada dipasang
           Laravel sendiri, `no-cache, private`, dan itu tidak cukup.
           `no-cache` berarti "tanyakan dulu sebelum dipakai" — berkasnya
           tetap boleh ditulis ke disk; `no-store` berarti "jangan
           ditulis sama sekali". Aturan "jangan menimpa" di berkas ini
           ada untuk menghindari tajuk kembar dengan nginx, dan
           Cache-Control bukan tajuk yang dipasang nginx.

           Lampiran sengaja dikecualikan: unduhan yang tidak boleh
           disimpan sementara gagal dibuka di sebagian peramban ponsel,
           dan berkas yang diunduh memang sudah mendarat di disk. */
        if ($request->user() && !$this->lampiran($tanggapan)) {
            $tanggapan->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        }

        if ($this->hsts($request) && !$tanggapan->headers->has('Strict-Transport-Security')) {
            $tanggapan->headers->set(
                'Strict-Transport-Security',
                'max-age='.(int) config('keamanan.hsts_umur', 31536000).'; includeSubDomains',
            );
        }

        return $tanggapan;
    }

    /** Tanggapan yang memang dimaksudkan untuk diunduh dan disimpan. */
    private function lampiran(Response $tanggapan): bool
    {
        return str_contains(
            (string) $tanggapan->headers->get('Content-Disposition'),
            'attachment',
        );
    }

    /**
     * HSTS hanya pada sambungan yang memang sudah https.
     *
     * Mengirimnya lewat http tidak berbahaya — peramban mengabaikan
     * HSTS yang datang tanpa TLS — tetapi juga tidak berguna, dan
     * memasang tajuk yang diabaikan membuat orang mengira
     * perlindungannya sudah berjalan.
     */
    private function hsts(Request $request): bool
    {
        return config('keamanan.hsts', false) && $request->isSecure();
    }
}
