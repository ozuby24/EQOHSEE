<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konsolidasi lintas modul.
 *
 * Sampai kini keenam modul hanya berbagi `company_id` dan `user_id`, sehingga
 * pekerjaan berhenti di batas modul: temuan audit SMKP tidak bisa mengalir ke
 * Hazard Report, dan satu SOP hidup terpisah di register dokumen maupun di
 * daftar prosedur LMS tanpa saling tahu.
 *
 * Migrasi ini memasang tiga sambungan yang hilang. Semuanya nullable — modul
 * tetap dapat dipakai sendiri-sendiri, tautan bersifat menambah, bukan syarat.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Dokumen terkendali ↔ prosedur LMS. Satu SOP cukup didaftarkan sekali
        // di register dokumen, lalu ditautkan ke prosedur yang dipakai pelatihan.
        Schema::table('documents', function (Blueprint $t) {
            $t->foreignId('procedure_id')->nullable()->after('company_id')
              ->constrained('procedures')->nullOnDelete();
        });

        // Temuan audit SMKP dapat dinaikkan menjadi Hazard Report, mengikuti
        // pola yang sudah dipakai temuan inspeksi.
        Schema::table('smkp_findings', function (Blueprint $t) {
            $t->foreignId('hazard_report_id')->nullable()->after('kode_kriteria')
              ->constrained('hazard_reports')->nullOnDelete();

            // Dokumen yang menjadi bukti pemenuhan kriteria.
            $t->foreignId('document_id')->nullable()->after('hazard_report_id')
              ->constrained('documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('smkp_findings', function (Blueprint $t) {
            $t->dropConstrainedForeignId('document_id');
            $t->dropConstrainedForeignId('hazard_report_id');
        });

        Schema::table('documents', function (Blueprint $t) {
            $t->dropConstrainedForeignId('procedure_id');
        });
    }
};
