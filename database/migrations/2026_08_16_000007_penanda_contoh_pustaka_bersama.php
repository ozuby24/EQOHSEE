<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda "ini data contoh" bagi pustaka yang dipakai bersama.
 *
 * Kursus, kuis, dan template inspeksi sengaja tidak melekat perusahaan
 * — keputusan yang sudah diambil dan dikunci LingkupPustakaTest.
 * Akibat sampingannya: penghapus data contoh tidak punya company_id
 * untuk disebut, sehingga ketiganya tidak dapat diisi contoh sama
 * sekali. Modul LMS karena itu satu-satunya yang tetap kosong sesudah
 * tombol "muat data contoh" ditekan.
 *
 * Kolom `demo_company_id` menyelesaikannya tanpa membatalkan keputusan
 * itu. Ia memisahkan dua hal yang tampak sama tetapi berbeda:
 *
 *   TERLIHAT OLEH SIAPA — tetap semua orang; pustaka ini memang
 *   bersama, dan kolom ini tidak menyaring pembacaan sama sekali.
 *
 *   DIBUANG BERSAMA SIAPA — perusahaan contoh yang membuatnya.
 *
 * Penanda boolean sederhana tidak cukup, dan itu ketahuan dari uji
 * yang sudah ada: dengan dua perusahaan contoh, "buang data contoh"
 * pada yang satu ikut membuang pustaka contoh milik yang lain, dan
 * hitungan yang ditampilkan sebelum menekan tombolnya pun salah.
 *
 * NULL berarti bukan data contoh — dan itulah bawaannya, yang membuat
 * kurikulum sungguhan aman: penghapus hanya menyentuh baris yang
 * secara tegas menyebut perusahaan contoh pemiliknya.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABEL = ['courses', 'quizzes', 'inspection_templates'];

    public function up(): void
    {
        foreach (self::TABEL as $t) {
            Schema::table($t, function (Blueprint $b) {
                $b->unsignedBigInteger('demo_company_id')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $t) {
            Schema::table($t, fn (Blueprint $b) => $b->dropColumn('demo_company_id'));
        }
    }
};
