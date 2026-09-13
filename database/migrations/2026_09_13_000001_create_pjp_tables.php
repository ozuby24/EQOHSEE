<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perusahaan Jasa Pertambangan (PJP).
 *
 * Pemegang IUP bertanggung jawab atas perusahaan jasa yang bekerja di
 * wilayahnya — bukan hanya atas pekerjanya sendiri. Modul ini memantau
 * PJP lewat TIGA aspek yang berjalan BERSAMAAN, bukan tahapan berurutan
 * yang dilewati satu per satu:
 *
 *   1. Persyaratan, Seleksi & Penetapan — skor checklist prakualifikasi SMKP
 *   2. Tanggung Jawab, Pemantauan & Pelaporan — kepatuhan unggah dokumen
 *   3. Evaluasi — skor kinerja per semester
 *
 * Karena itu TIDAK ada kolom `tahapan` di sini. Aplikasi asal sempat
 * punya kolom itu beserta tombol "Lanjutkan ke Tahap Berikutnya", dan
 * akibatnya satu PJP hanya muncul di satu halaman: checklist yang sudah
 * diisi hilang dari pandangan begitu PJP-nya "maju" ke pelaporan.
 * Ketiganya independen — sebuah checklist, sebuah laporan, dan sebuah
 * evaluasi untuk perusahaan yang sama tidak harus terjadi berurutan.
 *
 * `company_id` adalah pemegang izin yang memantau, BUKAN PJP-nya
 * sendiri. Tanpa kolom itu seluruh daftar PJP terlihat oleh semua
 * perusahaan di pemasangan ini — dan yang bocor bukan angka umum,
 * melainkan skor kepatuhan rekanan beserta dokumen yang diunggahnya.
 *
 * Tabel checklist (kategori dan pertanyaan) sengaja TIDAK bercompany_id:
 * isinya data acuan tetap dari dokumen prakualifikasi SMKP, sama bagi
 * setiap perusahaan. Yang menjadi milik perusahaan adalah jawabannya,
 * dan jawaban itu mewarisi batasnya dari PJP yang menjawab.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pjps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('nama_perusahaan');
            $t->string('nib')->nullable();
            $t->string('penanggung_jawab')->nullable();
            $t->text('alamat')->nullable();

            // aktif | perlu_tindak_lanjut | tidak_aktif — lihat Pjp::STATUS.
            $t->string('status')->default('aktif');
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'status']);
        });

        Schema::create('pjp_laporans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pjp_id')->constrained()->cascadeOnDelete();

            // spip | tsp | laporan_bulanan | laporan_triwulan
            $t->string('jenis');
            $t->string('periode')->nullable();

            $t->string('file_path');
            $t->string('file_name');
            $t->unsignedBigInteger('file_size')->default(0);
            $t->text('catatan')->nullable();

            /*
             * Penilaian manusia atas isi dokumen: sesuai | tidak_sesuai |
             * NULL (belum dinilai). Berdiri sendiri dari `tepat_waktu`
             * yang dihitung otomatis dari created_at — dokumen bisa
             * datang tepat waktu tetapi isinya tidak sesuai, dan
             * sebaliknya. Menggabungkan keduanya menjadi satu kolom
             * status membuat kedua kegagalan itu tidak terbedakan.
             */
            $t->string('kesesuaian_isi')->nullable();
            $t->timestamps();

            $t->index(['pjp_id', 'jenis']);
        });

        Schema::create('pjp_evaluasis', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pjp_id')->constrained()->cascadeOnDelete();

            $t->unsignedSmallInteger('tahun');
            $t->unsignedTinyInteger('semester');

            $t->unsignedTinyInteger('skor_teknis');
            $t->unsignedTinyInteger('skor_keselamatan_kesehatan');
            $t->unsignedTinyInteger('skor_lingkungan');
            $t->text('catatan')->nullable();
            $t->timestamps();

            /*
             * Satu baris per (PJP, tahun, semester). Kunci unik ini yang
             * membuat pengisian ulang semester yang sama MENIMPA, bukan
             * menambah baris kedua — tanpa itu, dua penilaian berbeda
             * untuk satu semester dapat hidup berdampingan dan "evaluasi
             * terakhir" menjadi bergantung pada urutan penyisipan.
             */
            $t->unique(['pjp_id', 'tahun', 'semester']);
        });

        // Data acuan: 17 kategori (LEGALITAS + A–P).
        Schema::create('smkp_checklist_categories', function (Blueprint $t) {
            $t->id();
            $t->string('kode')->unique();
            $t->string('nama');
            $t->unsignedInteger('bobot');
            $t->unsignedInteger('urutan');
            $t->timestamps();
        });

        // Data acuan: 126 pertanyaan berbobot.
        Schema::create('smkp_checklist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('smkp_checklist_category_id')->constrained()->cascadeOnDelete();

            $t->string('grup_kode')->nullable();
            $t->string('grup_nama')->nullable();
            $t->unsignedInteger('nomor');
            $t->text('pertanyaan');
            $t->text('petunjuk')->nullable();
            $t->unsignedInteger('bobot');
            $t->unsignedInteger('urutan');
            $t->timestamps();
        });

        Schema::create('smkp_checklist_answers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pjp_id')->constrained()->cascadeOnDelete();
            $t->foreignId('smkp_checklist_item_id')->constrained()->cascadeOnDelete();

            $t->string('jawaban')->nullable();   // ya | tidak | na
            $t->string('nilai')->nullable();     // 0 | 1 | 2 | 3 | na
            $t->text('penjelasan')->nullable();
            $t->timestamps();

            // Satu jawaban per pertanyaan per PJP; pengisian ulang menimpa.
            $t->unique(['pjp_id', 'smkp_checklist_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smkp_checklist_answers');
        Schema::dropIfExists('smkp_checklist_items');
        Schema::dropIfExists('smkp_checklist_categories');
        Schema::dropIfExists('pjp_evaluasis');
        Schema::dropIfExists('pjp_laporans');
        Schema::dropIfExists('pjps');
    }
};
