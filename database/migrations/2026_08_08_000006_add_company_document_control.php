<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data kendali dokumen perusahaan.
 *
 * Kop pada berkas audit memuat divisi, departemen, tanggal penerbitan,
 * tanggal persetujuan, dan nomor revisi. Semuanya milik perusahaan, bukan
 * milik satu audit — satu kali ditetapkan, seluruh berkasnya ikut benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->string('divisi')->nullable()->after('doc_no_prefix');
            $t->string('departemen')->nullable()->after('divisi');
            $t->date('doc_terbit')->nullable()->after('departemen');
            $t->date('doc_setuju')->nullable()->after('doc_terbit');
            $t->unsignedSmallInteger('doc_revisi')->default(0)->after('doc_setuju');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->dropColumn(['divisi', 'departemen', 'doc_terbit', 'doc_setuju', 'doc_revisi']);
        });
    }
};
