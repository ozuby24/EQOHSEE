<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Kode sekali pakai berbasis waktu (TOTP, RFC 6238).
 *
 * Ditulis sendiri, bukan lewat pustaka luar, dan alasannya bukan
 * keengganan memasang dependensi. Seluruh algoritmanya adalah HMAC-SHA1
 * atas nomor langkah waktu, lalu enam angka terakhir yang dipotong
 * dengan cara yang ditetapkan RFC — kira-kira empat puluh baris yang
 * tidak akan pernah berubah, karena RFC-nya tidak akan pernah berubah.
 * Yang ditukar dengan memasang pustaka bukan kerumitan itu, melainkan
 * satu rantai pemasok baru pada berkas yang menjaga pintu masuk.
 *
 * Kebenarannya tidak perlu dipercaya begitu saja: RFC 6238 menerbitkan
 * tabel kode yang benar untuk waktu-waktu tertentu, dan TotpTest
 * mencocokkan seluruh tabel itu. Kalau ada satu huruf yang salah di
 * sini, tabelnya yang akan memberitahu, bukan pengguna yang tiba-tiba
 * tidak bisa masuk.
 *
 * Yang dipakai Google Authenticator, Authy, 1Password, dan aplikasi
 * sejenis adalah setelan bawaan RFC: SHA-1, enam angka, langkah tiga
 * puluh detik. Ketiganya tidak boleh diubah tanpa mengubah QR yang
 * sudah terlanjur dipindai orang.
 */
class Totp
{
    /** Panjang satu langkah waktu, dalam detik. */
    public const LANGKAH = 30;

    /** Banyak angka pada kodenya. */
    public const ANGKA = 6;

    /**
     * Berapa langkah ke belakang dan ke depan yang masih diterima.
     *
     * Satu langkah, bukan nol. Jam ponsel dan jam peladen tidak pernah
     * sama persis, dan orang mengetik kode yang muncul sesaat sebelum
     * pergantian langkah. Toleransi nol menghasilkan kode yang ditolak
     * padahal benar, secara acak, pada kira-kira satu dari sepuluh
     * percobaan — bentuk kegagalan yang paling sulit dipercaya oleh yang
     * mengalaminya dan paling sulit ditiru oleh yang dimintai tolong.
     *
     * Lebih dari satu memperlebar jendela tebakan tanpa menolong siapa
     * pun yang jamnya wajar.
     */
    public const TOLERANSI = 1;

    private const ABJAD32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Rahasia baru, 160 bit, dalam base32.
     *
     * random_bytes, bukan mt_rand atau uniqid: yang menebak rahasia ini
     * menebak seluruh lapisan kedua sekaligus, dan tidak ada gejala apa
     * pun yang muncul dari rahasia yang dapat ditebak.
     */
    public static function rahasiaBaru(): string
    {
        return self::ke32(random_bytes(20));
    }

    /**
     * Kode yang benar untuk satu langkah waktu.
     *
     * @param  string    $rahasia  base32
     * @param  int|null  $waktu    detik Unix; null berarti sekarang
     */
    public static function kode(string $rahasia, ?int $waktu = null): string
    {
        $langkah = intdiv($waktu ?? self::sekarang(), self::LANGKAH);

        return self::kodeLangkah($rahasia, $langkah);
    }

    /**
     * Mencocokkan kode yang diketik orang.
     *
     * Memulangkan nomor langkah yang cocok, atau null. Nomornya perlu
     * dipulangkan — bukan sekadar benar atau salah — karena pemanggilnya
     * harus MENYIMPAN langkah yang sudah terpakai. Kode TOTP berlaku
     * sampai satu setengah menit dengan toleransi ini, dan tanpa catatan
     * itu kode yang terbaca dari balik bahu seseorang dapat dipakai lagi
     * sampai masa berlakunya habis.
     *
     * @param  string    $kode     apa adanya dari formulir
     * @param  int|null  $sesudah  tolak langkah yang lebih lama atau sama
     */
    public static function cocok(
        string $rahasia,
        string $kode,
        ?int $sesudah = null,
        ?int $waktu = null,
    ): ?int {
        /* Spasi dan tanda hubung dibuang. Aplikasi autentikator
           menampilkan "123 456", dan orang menyalinnya apa adanya. */
        $kode = preg_replace('/[^0-9]/', '', $kode) ?? '';

        if (strlen($kode) !== self::ANGKA) return null;

        $kini = intdiv($waktu ?? self::sekarang(), self::LANGKAH);

        for ($geser = -self::TOLERANSI; $geser <= self::TOLERANSI; $geser++) {
            $langkah = $kini + $geser;

            if ($sesudah !== null && $langkah <= $sesudah) continue;

            /* hash_equals, bukan ===. Perbandingan biasa berhenti pada
               huruf pertama yang berbeda, dan selisih waktunya — sangat
               kecil, tetapi terukur lewat ribuan percobaan — membocorkan
               berapa angka pertama yang sudah benar. Itu mengubah
               tebakan sejuta kemungkinan menjadi enam kali sepuluh. */
            if (hash_equals(self::kodeLangkah($rahasia, $langkah), $kode)) {
                return $langkah;
            }
        }

        return null;
    }

    /**
     * Alamat otpauth:// yang dipindai dari QR.
     *
     * Penerbitnya ikut dua kali — sebagai awalan label DAN sebagai
     * parameter issuer — dan itu memang yang diminta Google. Aplikasi
     * lama membaca yang pertama, yang baru membaca yang kedua; salah
     * satunya saja menghasilkan entri tanpa nama pada sebagian aplikasi.
     */
    public static function uri(string $rahasia, string $akun, string $penerbit): string
    {
        $label = rawurlencode($penerbit).':'.rawurlencode($akun);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret'    => $rahasia,
            'issuer'    => $penerbit,
            'algorithm' => 'SHA1',
            'digits'    => self::ANGKA,
            'period'    => self::LANGKAH,
        ]);
    }

    /**
     * Waktu sekarang lewat Carbon, bukan time().
     *
     * Bukan kerapian: time() tidak tunduk pada Carbon::setTestNow, jadi
     * uji yang memajukan jam — kode kedaluwarsa, kode sekali pakai,
     * setengah-masuk yang habis waktunya — akan memajukan seluruh
     * aplikasi KECUALI mesin yang justru sedang diuji. Yang dihasilkan
     * bukan uji yang gagal melainkan uji yang lulus tanpa menguji
     * apa pun, karena kodenya tidak pernah benar-benar berpindah
     * langkah.
     */
    private static function sekarang(): int
    {
        return Carbon::now()->getTimestamp();
    }

    private static function kodeLangkah(string $rahasia, int $langkah): string
    {
        $kunci = self::dari32($rahasia);

        /* Nomor langkahnya dikirim sebagai delapan bita, urutan besar
           dulu. pack('J') hanya ada pada PHP 64-bit, yang sudah syarat
           aplikasi ini. */
        $sirip = hash_hmac('sha1', pack('J', $langkah), $kunci, true);

        /* Pemotongan dinamis: empat bit terakhir menunjuk dari mana
           empat bita yang dipakai diambil. Ditetapkan RFC 4226 §5.3. */
        $awal  = ord($sirip[19]) & 0x0F;
        $angka = (
            ((ord($sirip[$awal])     & 0x7F) << 24) |
            ((ord($sirip[$awal + 1]) & 0xFF) << 16) |
            ((ord($sirip[$awal + 2]) & 0xFF) << 8)  |
             (ord($sirip[$awal + 3]) & 0xFF)
        ) % (10 ** self::ANGKA);

        return str_pad((string) $angka, self::ANGKA, '0', STR_PAD_LEFT);
    }

    private static function ke32(string $bita): string
    {
        $bit = '';
        foreach (str_split($bita) as $b) {
            $bit .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        }

        $keluar = '';
        foreach (str_split($bit, 5) as $lima) {
            $keluar .= self::ABJAD32[bindec(str_pad($lima, 5, '0', STR_PAD_RIGHT))];
        }

        return $keluar;
    }

    private static function dari32(string $base32): string
    {
        /* Huruf kecil dan padding '=' diterima: orang menyalin rahasia
           dari layar, dan aplikasi menuliskannya dengan gaya
           masing-masing. */
        $base32 = strtoupper(rtrim(trim($base32), '='));

        $bit = '';
        foreach (str_split($base32) as $huruf) {
            $nilai = strpos(self::ABJAD32, $huruf);

            if ($nilai === false) continue;

            $bit .= str_pad(decbin($nilai), 5, '0', STR_PAD_LEFT);
        }

        $bita = '';
        foreach (str_split($bit, 8) as $delapan) {
            if (strlen($delapan) === 8) $bita .= chr(bindec($delapan));
        }

        return $bita;
    }
}
