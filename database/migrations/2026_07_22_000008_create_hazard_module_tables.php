<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website #3 — Hazard Report & Inspeksi.
 * Konversi dari aplikasi Google Apps Script (Hazrep/Code.gs + Index.html).
 *
 * Sudah tertutup inti bersama: users (pelapor), companies (pelapor & terlapor),
 * activity_log (module = 'hazrep').
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hazard_reports', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique();                       // ID Laporan (HR-2026-0001)

            // Pelapor
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('pelapor_nama');
            $t->string('pelapor_nrp')->nullable();               // Employee ID
            $t->string('pelapor_perusahaan')->nullable();
            $t->string('pelapor_departemen')->nullable();
            $t->string('pelapor_jabatan')->nullable();

            // Terlapor
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('terlapor')->nullable();                  // Ditujukan kepada

            // Temuan
            $t->date('tanggal');
            $t->time('waktu')->nullable();
            $t->string('lokasi')->nullable();
            $t->string('risiko')->default('Sedang');            // Rendah | Sedang | Tinggi
            $t->string('kategori')->nullable();
            $t->text('deskripsi');
            $t->text('unsafe_action')->nullable();
            $t->text('unsafe_condition')->nullable();

            // Rekomendasi (hirarki pengendalian)
            $t->string('hirarki')->nullable();                  // Eliminasi..APD
            $t->text('rekomendasi')->nullable();

            // Tindak lanjut
            $t->string('status')->default('Open');              // Open | In Progress | Closed
            $t->json('foto')->nullable();                       // path foto temuan
            $t->json('foto_tindaklanjut')->nullable();
            $t->text('catatan_penutupan')->nullable();
            $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('closed_at')->nullable();

            $t->timestamps();
            $t->index(['status','tanggal']);
            $t->index('company_id');
        });

        // ── Inspeksi (bagian baru, melengkapi Hazard Report) ──
        Schema::create('inspections', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique();                       // INS-2026-0001
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('judul');
            $t->string('jenis')->nullable();                    // Harian | Mingguan | Bulanan | Khusus
            $t->string('lokasi')->nullable();
            $t->date('tanggal');
            $t->string('pelaksana')->nullable();
            $t->string('status')->default('Berjalan');          // Berjalan | Selesai
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->index(['status','tanggal']);
        });

        Schema::create('inspection_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $t->string('uraian');
            $t->string('kondisi')->nullable();                  // Sesuai | Tidak Sesuai | N/A
            $t->string('risiko')->nullable();                   // Rendah | Sedang | Tinggi
            $t->text('temuan')->nullable();
            $t->text('tindakan')->nullable();
            $t->json('foto')->nullable();
            $t->foreignId('hazard_report_id')->nullable()        // temuan dinaikkan jadi Hazard Report
              ->constrained('hazard_reports')->nullOnDelete();
            $t->integer('order_index')->default(1);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_items');
        Schema::dropIfExists('inspections');
        Schema::dropIfExists('hazard_reports');
    }
};
