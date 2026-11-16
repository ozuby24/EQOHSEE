<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan Akses Audit per perusahaan (port DBEST #16).
 *
 *   peran = auditor     akun yang boleh mengaudit perusahaan itu (Audit SMKP,
 *                       Audit Lingkungan): menilai, verifikasi bukti/RTP,
 *                       mengisi Tahap I/Rencana/Laporan.
 *   peran = pengunggah  akun perusahaan yang mengunggah bukti dan menjawab
 *                       temuan/RTP — hanya untuk perusahaannya.
 *
 * Satu akun satu peran per perusahaan (unik company_id + user_id): aturan
 * independensi — auditor tidak sekaligus pengunggah perusahaan yang sama.
 * Perusahaan tanpa satu baris pun memakai aturan bawaan (jangkauan grup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_akses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('peran', 12);
            $t->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'user_id']);
            $t->index(['user_id', 'peran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_akses');
    }
};
