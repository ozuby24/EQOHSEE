<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rencana Tindak Perbaikan (RTP) Audit Kinerja Lingkungan — mengikuti
 * Audit Lingkungan DBEST.
 *
 * Satu baris per kriteria yang nilai VERIFIKASI-nya ≤ 2 (bukan 3 dan bukan
 * kosong). Barisnya TIDAK dibuat tangan: terbit, dibuka ulang, atau ditutup
 * otomatis setiap kali nilai disimpan (App\Support\RtpLingkungan::perbarui).
 *
 *   status      buka | proses | selesai
 *   verifikasi  null (belum diajukan) | menunggu | disetujui | ditolak
 *   nilai       nilai verifikasi saat temuan terbit (urut terparah dulu)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('env_audit_rtps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained('env_audits')->cascadeOnDelete();
            $t->string('kode', 24);

            $t->unsignedTinyInteger('nilai')->default(0);
            $t->text('temuan')->nullable();
            $t->text('rencana')->nullable();
            $t->string('pic', 150)->nullable();
            $t->date('target_tanggal')->nullable();

            $t->string('status', 10)->default('buka');
            $t->date('tanggal_selesai')->nullable();
            $t->text('catatan_penutup')->nullable();

            $t->json('bukti')->nullable();
            $t->json('bukti_nama')->nullable();

            $t->string('verifikasi', 12)->nullable();
            $t->text('catatan_verifikasi')->nullable();
            $t->foreignId('verifikator_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('verifikator', 190)->nullable();
            $t->timestamp('diverifikasi_pada')->nullable();
            $t->timestamp('diajukan_pada')->nullable();

            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['audit_id', 'kode']);
            $t->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('env_audit_rtps');
    }
};
