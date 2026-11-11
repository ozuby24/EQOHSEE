<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda asal baris Miners — "Upload Data Lama" Safe Track menulis
 * remarks 'Impor data lama'. Di sini kolomnya sendiri (`asal`), supaya
 * baris hasil impor dapat disaring dan dihitung tanpa membaca catatan
 * bebas. Kosong = dibuat lewat alur biasa. Hanya menambah kolom.
 */
return new class extends Migration
{
    private const TABEL = ['mnr_pekerja', 'mnr_mcu', 'mnr_induksi', 'mnr_permit', 'mnr_simper'];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            if (Schema::hasColumn($tabel, 'asal')) continue;
            Schema::table($tabel, fn (Blueprint $t) => $t->string('asal', 20)->nullable());
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            if (! Schema::hasColumn($tabel, 'asal')) continue;
            Schema::table($tabel, fn (Blueprint $t) => $t->dropColumn('asal'));
        }
    }
};
