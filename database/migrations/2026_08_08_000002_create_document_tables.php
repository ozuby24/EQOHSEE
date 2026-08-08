<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ISO & Dokumen — kendali dokumen terpusat.
 *
 * Menyimpan register dokumen beserta riwayat revisinya. Berbeda dari tabel
 * `procedures` yang hanya menautkan SOP ke evaluasi LMS, tabel ini memegang
 * status keberlakuan, nomor revisi, masa tinjau, dan berkas terkendali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique();               // mis. SOP-K3-001
            $t->string('judul');
            $t->string('jenis');                        // Kebijakan | Manual | Prosedur | Instruksi Kerja | Formulir | Rekaman
            $t->string('klasifikasi')->nullable();      // Umum | Internal | Rahasia
            $t->string('departemen')->nullable();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->unsignedInteger('revisi')->default(0);
            $t->string('status')->default('draft');     // draft | berlaku | kadaluarsa | ditarik

            $t->date('tanggal_terbit')->nullable();
            $t->date('tanggal_berlaku')->nullable();
            $t->date('tanggal_tinjau')->nullable();     // jatuh tempo peninjauan berkala

            $t->string('berkas')->nullable();           // path Storage revisi berjalan
            $t->text('ringkasan')->nullable();
            $t->string('acuan')->nullable();            // klausul ISO / elemen SMKP terkait
            $t->string('disetujui_oleh')->nullable();

            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();

            $t->index(['status', 'jenis']);
            $t->index('tanggal_tinjau');
        });

        Schema::create('document_revisions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('revisi');
            $t->text('ringkasan_perubahan')->nullable();
            $t->string('berkas')->nullable();
            $t->date('tanggal')->nullable();
            $t->string('oleh')->nullable();
            $t->timestamps();

            $t->index(['document_id', 'revisi']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_revisions');
        Schema::dropIfExists('documents');
    }
};
