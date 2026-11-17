<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bagian naratif Laporan Audit Internal SMKP.
 *
 * Laporan yang dibaca inspektur tambang bukan hanya angka. Berkas acuan
 * (PT Indo Sejahtera Manunggal Site PT Multi Harapan Utama, 2023) memuat
 * — sebelum satu pun nilai disebut — latar belakang beserta dasar
 * hukumnya, gambaran umum auditi (domisili, legalitas, kegiatan,
 * peralatan, tenaga kerja), ringkasan penerapan tiap elemen, lingkup
 * audit, daftar lampiran, dan daftar distribusi laporan.
 *
 * Tanpa bagian itu, "39,14%" adalah angka tanpa perusahaan di belakangnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smkp_audits', function (Blueprint $t) {
            $t->json('laporan')->nullable()->after('sampel');
        });
    }

    public function down(): void
    {
        Schema::table('smkp_audits', function (Blueprint $t) {
            $t->dropColumn('laporan');
        });
    }
};
