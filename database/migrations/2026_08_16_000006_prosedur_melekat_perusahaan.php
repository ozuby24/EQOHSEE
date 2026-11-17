<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Prosedur melekat pada perusahaan yang memberlakukannya.
 *
 * Membetulkan pembagian yang sebelumnya menyamakan dua hal berbeda.
 * PELATIHAN — kursus dan kuis — memang dipakai bersama: materinya sama
 * bagi siapa pun yang belajar, dan menggandakannya per perusahaan
 * berarti setiap perbaikan harus dikerjakan berulang kali. PROSEDUR
 * tidak begitu: ia menyebut nama jabatan, batas kewenangan, dan
 * urutan kerja yang berlaku di satu perusahaan, dan perusahaan lain
 * tidak terikat olehnya. Prosedur milik orang lain yang terbaca sebagai
 * milik sendiri adalah kesalahan yang mahal pada audit.
 *
 * Kolomnya BOLEH KOSONG, dan tidak ada backfill — sama seperti pada
 * berita, dan karena alasan yang sama. Scope MilikPerusahaan
 * memperlakukan baris tanpa perusahaan sebagai milik bersama, jadi:
 *
 *   - Prosedur yang sudah ada tetap terbaca semua orang. Menebak
 *     pemiliknya sekarang akan menyembunyikannya dari yang berhak, dan
 *     kegagalan itu tidak menimbulkan galat apa pun — hanya daftar
 *     prosedur yang mendadak kosong.
 *   - Prosedur baru mewarisi perusahaan pembuatnya.
 *   - Prosedur baku se-pemasangan tetap mungkin: dibuat administrator
 *     yang tidak terikat perusahaan.
 *
 * Anak-anaknya — sop_evaluations dan documents — tidak diubah di sini.
 * Documents sudah punya company_id sendiri, dan evaluasi SOP mengikuti
 * prosedurnya lewat relasi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedures', function (Blueprint $t) {
            $t->unsignedBigInteger('company_id')->nullable()->after('id');
            $t->index(['company_id', 'category'], 'procedures_company_kategori_idx');
        });
    }

    public function down(): void
    {
        Schema::table('procedures', function (Blueprint $t) {
            $t->dropIndex('procedures_company_kategori_idx');
            $t->dropColumn('company_id');
        });
    }
};
