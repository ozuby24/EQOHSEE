<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Nama asli dokumen bukti audit lingkungan, sejajar dengan `berkas`.
 *
 * Berkas disimpan dengan nama acak (aman), tetapi auditor mencari bukti
 * menurut namanya — "Izin TPS LB3.pdf", bukan "lampiran 3". Tanpa kolom
 * ini daftar bukti hanya dapat menyebut urutannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('env_audit_scores', function (Blueprint $t) {
            $t->json('berkas_nama')->nullable()->after('berkas');
        });
    }

    public function down(): void
    {
        Schema::table('env_audit_scores', function (Blueprint $t) {
            $t->dropColumn('berkas_nama');
        });
    }
};
