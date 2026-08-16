<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Singkatan departemen, untuk penomoran FRM/CAM/OHSE/001.
 *
 * Kolom `departemen` yang sudah ada memuat nama panjangnya —
 * "Occupational Health, Safety and Environment" — dan itu memang yang
 * dicetak pada baris departemen di kop. Yang dibutuhkan penomoran
 * adalah singkatannya, dan menyingkat nama panjang secara otomatis
 * bukan hal yang boleh ditebak: singkatan departemen ditetapkan
 * perusahaan, bukan diturunkan dari ejaannya.
 *
 * Bawaannya kosong, dan yang kosong dibaca sebagai OHSE — departemen
 * penerbit sebagian besar dokumen pada aplikasi ini. Perusahaan yang
 * menerbitkan dari departemen lain tinggal mengisinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->string('dept_kode', 12)->nullable()->after('departemen');
        });
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('dept_kode'));
    }
};
