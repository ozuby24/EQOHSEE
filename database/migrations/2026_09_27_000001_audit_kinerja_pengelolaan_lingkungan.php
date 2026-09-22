<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit Kinerja Pengelolaan dan Pemantauan Lingkungan.
 *
 * Instrumen audit internal ISO 14001 yang dipakai pemegang IUP menilai
 * mitra kerjanya: enam bagian, dua ratus satu kriteria bernilai 0–3,
 * berbobot, dikurangi nilai pengurang, lalu menghasilkan predikat
 * penghargaan dan peringkat warna.
 *
 * ── Dua kolom nilai, bukan satu ──
 *
 * Berkas acuannya memisahkan "Nilai" — yang diisi mitra sebagai
 * penilaian mandiri — dari "Hasil Verifikasi Lapangan" yang diisi
 * auditor. Yang menentukan skor akhir adalah kolom VERIFIKASI. Disatukan
 * menjadi satu kolom, penilaian mandiri mitra langsung menjadi skor
 * resminya, dan audit berubah menjadi formulir isian mandiri yang
 * ditandatangani auditor.
 *
 * ── Nilai disimpan menurut KODE kriteria, bukan urutan baris ──
 *
 * Kodenya b.3.1.f — bagian, subbagian, kelompok, huruf. Disimpan
 * menurut urutan baris, menyisipkan satu kriteria di tengah akan
 * menggeser seluruh nilai di bawahnya: tidak ada galat, hanya jawaban
 * yang menempel pada pertanyaan yang salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('env_audits', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('kode')->nullable();
            $t->integer('tahun');
            $t->string('judul');
            $t->string('lokasi')->nullable();
            $t->date('tanggal')->nullable();
            $t->string('status')->default('Berjalan');    // Berjalan | Selesai

            /* Profil perusahaan dari lembar "Informasi Umum": alamat,
               jumlah karyawan, kontak. Disimpan sebagai json karena ia
               potret identitas pada saat audit — bukan data master
               perusahaan yang berubah sendiri sesudahnya. */
            $t->json('profil')->nullable();

            /* Kunci nilai pengurang yang berlaku, mis. ["kecelakaan"]. */
            $t->json('pengurang')->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'tahun']);
        });

        Schema::create('env_audit_scores', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained('env_audits')->cascadeOnDelete();

            // Kode kriteria yang stabil, mis. "b.3.1.f"
            $t->string('kode', 32);

            $t->unsignedTinyInteger('nilai')->nullable();        // penilaian mandiri mitra
            $t->unsignedTinyInteger('verifikasi')->nullable();   // hasil verifikasi auditor

            $t->text('keterangan')->nullable();
            $t->json('berkas')->nullable();                      // dokumen pendukung
            $t->timestamps();

            $t->unique(['audit_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('env_audit_scores');
        Schema::dropIfExists('env_audits');
    }
};
