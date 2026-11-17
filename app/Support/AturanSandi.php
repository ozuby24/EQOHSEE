<?php

namespace App\Support;

/**
 * Aturan sandi dan pesan galatnya, dalam satu tempat.
 *
 * Aturannya sendiri dipasang di AppServiceProvider lewat
 * Password::defaults(), dan alasan setiap pilihannya ditulis lengkap di
 * sana. Yang disimpan di sini adalah angka minimalnya — supaya aturan
 * dan pesannya tidak dapat menyebut angka yang berbeda — serta pesannya
 * dalam bahasa Indonesia.
 *
 * ── Kenapa pesannya perlu ditulis sendiri ──
 *
 * Aplikasi ini tidak memuat berkas terjemahan validasi, jadi pesan
 * bawaan Laravel keluar dalam bahasa Inggris. Selama aturan sandinya
 * cuma "minimal delapan huruf", pesan itu nyaris tidak pernah terbaca
 * siapa pun. Sesudah pemeriksaan daftar bocoran dipasang, ia menjadi
 * pesan yang PALING sering muncul di halaman pendaftaran — dan
 * "The given password has appeared in a data leak" pada formulir yang
 * seluruhnya berbahasa Indonesia berakhir sebagai telepon ke
 * administrator, bukan sebagai sandi yang diganti.
 *
 * Pesannya tidak dipasang lewat terjemahan global karena kunci
 * validation.min.string berlaku untuk SEMUA kolom, bukan sandi saja:
 * menerjemahkannya di sana akan mengubah pesan puluhan formulir lain
 * yang tidak ikut diperiksa di sini.
 */
class AturanSandi
{
    /**
     * Panjang terpendek yang diterima.
     *
     * Disimpan sebagai tetapan, bukan ditulis dua kali. Angka yang
     * ditulis di aturan dan di pesannya secara terpisah akan berbeda
     * pada suatu hari, dan yang membacanya diberi tahu batas yang salah
     * oleh formulir yang menolaknya dengan batas yang lain.
     */
    public const MINIMAL = 12;

    /**
     * Pesan galat untuk satu kolom sandi.
     *
     * @param  string  $kolom  nama kolomnya pada formulir
     * @return array<string, string>
     */
    public static function pesan(string $kolom = 'password'): array
    {
        return [
            $kolom.'.min' => 'Kata sandi minimal '.self::MINIMAL.' huruf. '
                .'Kalimat pendek lebih mudah diingat sekaligus lebih sulit ditebak '
                .'daripada satu kata bercampur angka.',

            /* Menyebut apa yang terjadi, bukan menyalahkan orangnya.
               Sandi yang bocor umumnya bocor dari situs LAIN, dan yang
               membaca pesan ini perlu tahu itu — kalau tidak, ia
               menyangka akun EQOHSEE-nya yang sudah dibobol. */
            $kolom.'.uncompromised' => 'Kata sandi ini pernah muncul pada kebocoran data di '
                .'internet, sehingga sudah ada di daftar tebakan penyerang. Kebocorannya '
                .'hampir selalu berasal dari situs lain, bukan dari sini. Pilih kata sandi '
                .'yang berbeda, dan ganti juga di situs lain bila Anda memakainya di sana.',

            $kolom.'.confirmed' => 'Ulangan kata sandinya tidak sama.',
        ];
    }
}
