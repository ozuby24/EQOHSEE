<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lapisan kedua saat masuk: kode sekali pakai dari aplikasi autentikator.
 *
 * Empat lajur, dan pemisahan antara yang pertama dan yang ketiga adalah
 * inti rancangannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            /* Rahasia TOTP, tersandi saat disimpan (lihat cast di User).
               Yang memegang isi lajur ini dapat membuat kode yang sah
               kapan saja tanpa menyentuh ponsel siapa pun — jadi ia
               tidak boleh terbaca dari cadangan basis data yang bocor. */
            $t->text('dua_faktor_rahasia')->nullable()->after('remember_token');

            /* Kode pemulihan, juga tersandi. Disimpan dapat dibaca
               kembali — bukan disirip — supaya pemiliknya bisa
               melihatnya lagi dari sesi yang sudah masuk. Kode yang
               disirip lebih aman di atas kertas, tetapi hanya dapat
               ditampilkan SEKALI, dan yang menutup halamannya terlalu
               cepat kehilangan satu-satunya jalan pulang. Pada aplikasi
               yang dipakai dari site tambang, jalan pulang itu lebih
               berharga daripada selisih keamanannya. */
            $t->text('dua_faktor_pemulihan')->nullable()->after('dua_faktor_rahasia');

            /* Kapan lapisan ini benar-benar MENYALA.
             *
             * Terpisah dari rahasianya, dan itu yang mencegah bentuk
             * penguncian yang paling mudah terjadi: orang memindai QR,
             * rahasianya tersimpan, lalu ia menutup halaman tanpa pernah
             * membuktikan aplikasinya berhasil memasang. Kalau rahasia
             * yang tersimpan sudah cukup untuk menyalakannya, orang itu
             * terkunci di luar pada percobaan masuk berikutnya — oleh
             * fitur yang ia sendiri tidak yakin sudah ia pasang.
             *
             * Selama lajur ini null, rahasianya ada tetapi tidak
             * ditagih siapa pun. */
            $t->timestamp('dua_faktor_aktif_at')->nullable()->after('dua_faktor_pemulihan');

            /* Nomor langkah waktu terakhir yang sudah ditukar.
               Satu kode hidup sampai satu setengah menit; tanpa catatan
               ini, kode yang terbaca dari balik bahu dapat dipakai lagi
               selama sisa umurnya. */
            $t->unsignedBigInteger('dua_faktor_langkah')->nullable()->after('dua_faktor_aktif_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn([
                'dua_faktor_rahasia',
                'dua_faktor_pemulihan',
                'dua_faktor_aktif_at',
                'dua_faktor_langkah',
            ]);
        });
    }
};
