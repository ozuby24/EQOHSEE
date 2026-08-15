<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lepas tautan temuan SMKP → Hazard Report.
 *
 * Keduanya memang berbeda konteks: temuan audit adalah ketidaksesuaian
 * terhadap sistem manajemen, sedangkan Hazard Report adalah bahaya fisik
 * yang teramati di lapangan. Menyatukannya membuat kedua daftar itu
 * bercampur dan sulit dibaca.
 *
 * Tautan ke dokumen bukti (document_id) tetap dipertahankan — itu memang
 * satu konteks: kriteria audit dibuktikan oleh dokumen terkendali.
 *
 * Ditulis sebagai migrasi baru, bukan menyunting migrasi sebelumnya, agar
 * server yang sudah menjalankan migrasi lama tetap ikut terbersihkan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('smkp_findings', 'hazard_report_id')) {
            Schema::table('smkp_findings', function (Blueprint $t) {
                $t->dropConstrainedForeignId('hazard_report_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('smkp_findings', function (Blueprint $t) {
            $t->foreignId('hazard_report_id')->nullable()->after('kode_kriteria')
              ->constrained('hazard_reports')->nullOnDelete();
        });
    }
};
