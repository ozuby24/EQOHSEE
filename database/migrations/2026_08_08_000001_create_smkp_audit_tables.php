<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SMKP Minerba — audit 7 elemen sesuai Kepdirjen 185.K/37.04/DJB/2019.
 *
 * Satu baris per periode audit per perusahaan. Penilaian tiap kriteria
 * disimpan sebagai JSON (seperti modul PTPKKP) supaya struktur elemen bisa
 * berubah lewat resources/data/smkp/elemen.json tanpa migrasi ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smkp_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('tahun');
            $t->string('judul')->nullable();
            $t->string('status')->default('draft');      // draft | berjalan | selesai
            $t->date('tanggal_mulai')->nullable();
            $t->date('tanggal_selesai')->nullable();
            $t->string('ketua_auditor')->nullable();

            $t->json('hasil')->nullable();      // hasil[kodeKriteria] = {n, ket, bukti}
            $t->json('auditor')->nullable();    // [{nama, peran, kompetensi}]
            $t->json('profil')->nullable();     // ruang lingkup, lokasi, KTT, catatan

            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();

            $t->unique(['company_id', 'tahun']);
        });

        Schema::create('smkp_findings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained('smkp_audits')->cascadeOnDelete();
            $t->string('kode_kriteria');                  // mis. IV.2.1
            $t->string('jenis');                          // mayor | minor | observasi
            $t->text('uraian');
            $t->text('akar_masalah')->nullable();
            $t->text('tindakan')->nullable();             // tindakan perbaikan
            $t->string('penanggung_jawab')->nullable();
            $t->date('target_selesai')->nullable();
            $t->date('tanggal_selesai')->nullable();
            $t->string('status')->default('Open');        // Open | In Progress | Closed
            $t->text('verifikasi')->nullable();
            $t->timestamps();

            $t->index(['audit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smkp_findings');
        Schema::dropIfExists('smkp_audits');
    }
};
