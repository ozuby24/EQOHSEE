<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Isian lampiran "Respon Perusahaan Terhadap Pelaksanaan Audit Internal
 * SMKP" yang tidak dapat diturunkan dari data audit lain: tempat
 * penandatanganan ("Padang, 12 Desember 2025"). Nama perusahaan, tanggal
 * pelaksanaan, dan site diturunkan dari data audit yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smkp_audits', fn (Blueprint $t) => $t->json('respon')->nullable()->after('laporan'));
    }

    public function down(): void
    {
        Schema::table('smkp_audits', fn (Blueprint $t) => $t->dropColumn('respon'));
    }
};
