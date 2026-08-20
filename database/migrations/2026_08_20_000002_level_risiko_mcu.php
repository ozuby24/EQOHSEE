<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Level risiko kesehatan kerja pada catatan MCU.
 *
 * Kolom terakhir dari `mcu_details` D'Best yang belum ada di sini.
 * Bukan sekadar melengkapi daftar: "Fit" menjawab BOLEH ATAU TIDAK
 * orangnya bekerja, level risiko menjawab SEBERAPA DEKAT ia ke batas
 * itu. Keduanya berbeda dan yang kedua tidak dapat disimpulkan dari
 * yang pertama — seorang pekerja Fit dengan risiko Tinggi adalah orang
 * yang perlu diperiksa lebih sering dan diawasi penempatannya, dan
 * tanpa kolom ini ia tercatat persis sama dengan rekannya yang Fit
 * dengan risiko Rendah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paspor_mcu', function (Blueprint $t) {
            $t->string('level_risiko')->nullable()->after('hasil');
        });
    }

    public function down(): void
    {
        Schema::table('paspor_mcu', function (Blueprint $t) {
            $t->dropColumn('level_risiko');
        });
    }
};
