<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website #4 — Sistem Monitoring Keselamatan Operasi (KO / SPIP).
 * Konversi dari aplikasi Google Apps Script (KO/Code.gs + KO/Index.html).
 *
 * Sudah tertutup inti bersama (tidak dibuat ulang):
 *   companies  -> tabel companies (inti) — sheet 'Companies'
 *   users      -> tabel users + audit_role/ko_role (inti) — sheet 'Users'
 *   audit      -> tabel activity_log, module='ko' (inti) — sheet 'Audit'
 *   settings   -> tabel app_settings, key 'ko_*' (inti) — sheet 'Settings'
 *
 * Yang dibuat baru: 2 tabel di bawah (sheet 'SPIP' dan 'Tenaga').
 */
return new class extends Migration
{
    public function up(): void
    {
        // Objek KO — peralatan & instalasi yang wajib bersertifikat SPIP
        Schema::create('ko_objects', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique();              // id lama: EX-01, DT-01, IF-01, dst.
            $t->string('nama');
            $t->string('kategori');                     // Peralatan | Instalasi
            $t->string('jenis')->nullable();             // Excavator, Dump Truck, Bulldozer, dst.
            $t->string('merk')->nullable();
            $t->string('serial_number')->nullable();
            $t->string('lokasi')->nullable();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kritikalitas')->default('Sedang');   // Rendah | Sedang | Tinggi
            $t->string('status_operasi')->default('Aktif');  // Aktif | Breakdown | Standby | Non-Aktif

            // Sertifikasi kelaikan (Sub-elemen 3 SPIP)
            $t->date('tgl_sertifikasi')->nullable();
            $t->integer('interval_tahun')->default(3);       // masa berlaku: peralatan 3th, instalasi 5th
            $t->string('no_sertifikat')->nullable();
            $t->string('lembaga_uji')->nullable();
            $t->boolean('lapor_kait')->default(false);        // lapor KaIT ≤14 hari

            // Preventive Maintenance
            $t->string('pm_jenis')->nullable();
            $t->date('pm_terakhir')->nullable();
            $t->date('pm_berikutnya')->nullable();

            $t->timestamps();
            $t->index(['company_id','status_operasi']);
        });

        // Kajian teknis per objek (commissioning, modifikasi, insiden, dll.) — 1-ke-banyak
        Schema::create('ko_reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ko_object_id')->constrained('ko_objects')->cascadeOnDelete();
            $t->string('judul');
            $t->string('pemicu')->nullable();           // Awal operasi, Modifikasi, Insiden, Berkala...
            $t->date('tanggal')->nullable();
            $t->string('oleh')->nullable();              // nama tenaga teknis pengkaji
            $t->string('status')->default('Berjalan');   // Berjalan | Dilaporkan
            $t->date('tgl_lapor')->nullable();
            $t->timestamps();
        });

        // Tenaga teknis bersertifikat (Pengawas/Tenaga Teknis KO)
        Schema::create('ko_personnel', function (Blueprint $t) {
            $t->id();
            $t->string('nama');
            $t->string('jabatan')->nullable();           // Pengawas Teknis Mekanik, Tenaga Teknis Listrik...
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('sertifikasi')->nullable();        // Ahli K3 Pertambangan/POU, POP, dst.
            $t->string('no_sertifikat')->nullable();
            $t->date('tgl_kadaluarsa')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ko_personnel');
        Schema::dropIfExists('ko_reviews');
        Schema::dropIfExists('ko_objects');
    }
};
