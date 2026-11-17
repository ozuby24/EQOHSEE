<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit perusahaan grup (port DBEST #21): perusahaan lain satu grup — mis.
 * pemegang IUP lain — diaudit dengan nama yang diketik manual, TERPISAH
 * dari data perusahaan: tanpa company_id, tidak ikut dasbor, statistik,
 * pengingat, daftar tindak lanjut lintas audit, maupun perbandingan
 * antartahun, dan hanya dibuka administrator.
 *
 *   lingkup          null (bawaan) | 'grup'
 *   perusahaan_luar  nama perusahaan grup
 *   jenis_luar       'iup' (pemegang IUP) | 'iujp' (jasa pertambangan) —
 *                    menentukan rubrik dan kalimat kedudukan dokumen
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smkp_audits', function (Blueprint $t) {
            $t->string('lingkup', 10)->nullable()->index();
            $t->string('perusahaan_luar', 190)->nullable();
            $t->string('jenis_luar', 10)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('smkp_audits', function (Blueprint $t) {
            $t->dropIndex(['lingkup']);
            $t->dropColumn(['lingkup', 'perusahaan_luar', 'jenis_luar']);
        });
    }
};
