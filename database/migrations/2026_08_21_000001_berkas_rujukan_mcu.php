<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berkas surat rujukan — RUJUKANNYA sendiri, bukan sekadar sebutannya.
 *
 * Pada D'Best, `rujukan` adalah kolom BERKAS: yang diunggah paramedis
 * adalah surat rujukannya. Di sini `rujukan` sudah lebih dulu dipakai
 * sebagai keterangan bebas ("Poli Jantung — kontrol tekanan darah"),
 * dan keterangan itu berguna: ia yang tampil pada daftar dan yang
 * menjelaskan rujukannya tanpa perlu membuka berkas.
 *
 * Karena itu kolomnya DITAMBAH, bukan diubah artinya. Mengubah arti
 * `rujukan` menjadi jalur berkas akan membuat seluruh baris lama
 * terbaca sebagai jalur berkas yang tidak pernah ada — daftar tetap
 * tergambar, tombolnya tetap muncul, dan yang menekannya mendapat 404
 * tanpa tahu bahwa yang tersimpan memang bukan berkas.
 *
 * Keduanya berpasangan: `rujukan` menjawab "dirujuk ke mana",
 * `berkas_rujukan` menjawab "mana suratnya".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paspor_mcu', function (Blueprint $t) {
            $t->string('berkas_rujukan')->nullable()->after('rujukan');
        });
    }

    public function down(): void
    {
        Schema::table('paspor_mcu', function (Blueprint $t) {
            $t->dropColumn('berkas_rujukan');
        });
    }
};
