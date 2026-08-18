<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
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
        /* Nonce dibuat SEBELUM tanggapannya digambar, bukan sesudah.

           `$next($request)` sudah menghasilkan HTML yang jadi, jadi
           nonce yang dibuat sesudahnya tidak akan pernah masuk ke
           dalam tag <script> mana pun — tajuknya terpasang, skripnya
           terblokir, dan halamannya kosong tanpa satu pun galat di sisi
           server. */
        $nonce = self::$nonce = Str::random(24);
        Vite::useCspNonce($nonce);

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

        if (config('keamanan.csp', true) && !$tanggapan->headers->has('Content-Security-Policy')) {
            $tanggapan->headers->set('Content-Security-Policy', $this->csp($nonce));
        }

        if ($this->hsts($request) && !$tanggapan->headers->has('Strict-Transport-Security')) {
            $tanggapan->headers->set(
                'Strict-Transport-Security',
                'max-age='.(int) config('keamanan.hsts_umur', 31536000).'; includeSubDomains',
            );
        }

        return $tanggapan;
    }

    /**
     * Susun Content-Security-Policy.
     *
     * Yang benar-benar dijaga di sini adalah `script-src`. Selama
     * halaman boleh menjalankan skrip apa pun yang muncul di dalam
     * HTML-nya, satu isian yang lolos penyaringan cukup untuk menjalankan
     * kode atas nama siapa pun yang membuka halaman itu — dan pada
     * aplikasi ini yang membukanya termasuk administrator.
     *
     * `style-src` terpaksa memuat 'unsafe-inline'. Vue menulis gaya
     * langsung pada elemen untuk tiap pengikatan `:style`, dan akar
     * halaman sendiri membawa `style="..."` berisi warna tema. Menutupnya
     * berarti membongkar cara aplikasi ini menggambar warnanya. Kelonggaran
     * pada gaya jauh lebih kecil akibatnya daripada pada skrip: gaya dapat
     * dipakai membocorkan bentuk halaman, tetapi tidak dapat memanggil
     * apa pun atas nama penggunanya.
     *
     * Huruf dari Google Fonts disebut satu per satu. Membiarkan
     * `default-src 'self'` menutupnya akan membuat seluruh halaman
     * tergambar dengan huruf cadangan — perubahan yang terlihat oleh
     * semua orang dan tidak terbaca sebagai masalah keamanan, sehingga
     * diperbaiki dengan melonggarkan CSP-nya, bukan dengan menyebut
     * hurufnya.
     */
    private function csp(string $nonce): string
    {
        $skrip  = "'self' 'nonce-$nonce'";
        $sambung = "'self'";

        /* Saat `npm run dev` berjalan, berkas dilayani dari server Vite
           pada porta lain — asal yang berbeda menurut CSP. Tanpa
           kelonggaran ini, pengembangan berhenti bekerja sama sekali,
           dan cara tercepat memperbaikinya adalah mematikan CSP-nya —
           lalu lupa menyalakannya lagi. */
        if (Vite::isRunningHot()) {
            $asal = rtrim((string) config('vite.dev_server_url', 'http://localhost:5173'), '/');
            $skrip   .= " $asal";
            $sambung .= " $asal ws://localhost:5173 ws://127.0.0.1:5173";
        }

        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'self'",
            "form-action 'self'",
            "script-src $skrip",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob:",
            "media-src 'self'",
            "connect-src $sambung",
        ]);
    }

    /**
     * Nonce permintaan yang sedang berjalan.
     *
     * Dipakai app-inertia.blade.php. Diambil lewat Vite::cspNonce() bila
     * bisa; properti ini hanya cadangan supaya tampilan tidak pernah
     * menggambar tag tanpa nonce diam-diam — tag semacam itu tidak
     * memunculkan galat, hanya halaman yang tidak jalan.
     */
    private static ?string $nonce = null;

    public static function nonce(): string
    {
        return Vite::cspNonce() ?? self::$nonce ?? '';
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
