<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penyesuaian kop formulir audit SMKP per perusahaan (porting Aurorasys:
 * Master Data → Perusahaan → Kop dokumen terkendali).
 *
 * `kop_formulir` = {<kunci formulir>: {jenis?, judul?, kode?}}. Kosong
 * berarti kop bawaan EQOHSEE (nomor dari Support\Nomor, mis.
 * FRM/CAM/OHSE/001). Kode yang diisi mengganti nomornya menjadi bentuk
 * Aurorasys, {kode singkat perusahaan}-{kode} — mis. CAM-HSE-IV.067h —
 * sehingga administrator dapat menyamakan berkas cetak dengan penomoran
 * yang sudah beredar. Lihat KopDokumen::untuk().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('companies', 'kop_formulir')) return;

        Schema::table('companies', function (Blueprint $t) {
            $t->json('kop_formulir')->nullable()->after('doc_revisi');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('companies', 'kop_formulir')) return;

        Schema::table('companies', function (Blueprint $t) {
            $t->dropColumn('kop_formulir');
        });
    }
};
