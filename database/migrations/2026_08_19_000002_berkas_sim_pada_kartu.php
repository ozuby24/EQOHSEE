<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tempat menyimpan berkas SIM kepolisian itu sendiri.
 *
 * Nomornya dan masa berlakunya sudah punya kolom, tetapi dokumennya
 * belum. Tanpa berkasnya, satu-satunya bukti bahwa SIM-nya benar-benar
 * ada adalah tanggal yang diketik orang — dan tanggal yang diketik
 * tidak dapat diperiksa ulang saat auditor memintanya.
 *
 * Kolomnya juga yang membuat masa berlakunya dapat dibaca sendiri saat
 * berkasnya diunggah, alih-alih diketik dari layar ponsel di lapangan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paspor_kartu', function (Blueprint $t) {
            if (!Schema::hasColumn('paspor_kartu', 'berkas_sim')) {
                $t->string('berkas_sim')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('paspor_kartu', function (Blueprint $t) {
            if (Schema::hasColumn('paspor_kartu', 'berkas_sim')) $t->dropColumn('berkas_sim');
        });
    }
};
