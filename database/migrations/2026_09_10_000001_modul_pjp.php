<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemantauan Perusahaan Jasa Pertambangan (PJP).
 *
 * MENGAPA MODUL TERSENDIRI, BUKAN BAGIAN SMKP AUDIT.
 *
 * SMKP Audit menilai sistem keselamatan PEMEGANG IUP sendiri: tujuh
 * elemen Kepdirjen 185/2019, dinilai auditor internalnya, atas
 * pekerjaannya sendiri. Yang di sini menilai PIHAK LAIN — perusahaan
 * jasa yang dipekerjakan di wilayah izin itu — dengan pertanyaan,
 * bobot, dan akibat hukum yang berbeda: pemegang IUP bertanggung jawab
 * atas keselamatan pekerjaan yang dikerjakan mitranya, dan Inspektur
 * Tambang menanyakan bukti pemantauannya.
 *
 * Menyatukan keduanya ke satu tabel berarti setiap baris audit internal
 * harus menjawab lebih dulu "ini menilai siapa" sebelum satu angka pun
 * dapat dijumlahkan — dan rekap yang lupa bertanya akan menjumlahkan
 * nilai mitra ke dalam nilai sendiri tanpa satu galat pun muncul.
 *
 * MENGAPA TIGA TAHAP TIDAK DISIMPAN SEBAGAI KOLOM `tahapan`.
 *
 * Aslinya ada kolom `tahapan` yang menyebut satu tahap yang sedang
 * dijalani. Itu salah membaca prosesnya: satu PJP menjalani ketiganya
 * SEKALIGUS — persyaratannya dinilai, laporan bulanannya ditunggu, dan
 * kinerjanya dievaluasi tiap semester — bukan bergantian. Kolom itu
 * membuat halaman Pelaporan menyembunyikan setiap PJP yang kebetulan
 * "sedang di tahap Evaluasi", padahal laporan bulanannya tetap wajib.
 * Kolomnya dibuang di hulu, jadi tidak ikut dibuat di sini.
 *
 * ACUAN REGULASI — jangan diubah tanpa memeriksa teks resminya:
 *   · Permen ESDM 26/2018 dan Kepmen ESDM 1806 K/30/MEM/2018 — kewajiban
 *     pemegang IUP mengevaluasi kinerja perusahaan jasa pertambangan.
 *   · Kepdirjen Minerba 185/2019 Lampiran — daftar periksa prakualifikasi
 *     SMKP bagi perusahaan jasa: 17 kategori, bobot A–P berjumlah 178.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ═══════════════════ PERUSAHAAN JASA ═══════════════════
         *
         * company_id di sini BUKAN perusahaan jasanya sendiri,
         * melainkan pemegang IUP yang memantaunya. Satu perusahaan
         * jasa yang bekerja pada dua IUP muncul sebagai dua baris,
         * dan itu benar: penilaian, laporan, dan evaluasinya memang
         * dua berkas berbeda milik dua pihak yang berbeda, dan
         * masing-masing hanya boleh melihat berkasnya sendiri.
         */
        Schema::create('pjp_perusahaan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('nama_perusahaan', 200);
            $t->string('nib', 60)->nullable();
            $t->string('penanggung_jawab', 150)->nullable();
            $t->text('alamat')->nullable();

            /* aktif | perlu_tindak_lanjut | tidak_aktif — lihat
               App\Models\Pjp\Pjp::STATUS. */
            $t->string('status', 30)->default('aktif')->index();
            $t->text('catatan')->nullable();

            $t->timestamps();

            $t->index(['company_id', 'status']);
        });

        /* ═══════════════════ DOKUMEN BERKALA ═══════════════════
         *
         * `kesesuaian_isi` sengaja boleh kosong dan TIDAK berdefault
         * 'sesuai'. Kosong berarti "belum diperiksa siapa pun", dan itu
         * keadaan yang berbeda dari "sudah diperiksa dan memang sesuai".
         * Default 'sesuai' membuat skor pelaporan naik ke 100% pada
         * detik dokumennya diunggah — sebelum satu orang pun membukanya.
         */
        Schema::create('pjp_laporan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pjp_id')->constrained('pjp_perusahaan')->cascadeOnDelete();

            $t->string('jenis', 30)->index();
            $t->string('periode', 60)->nullable();

            $t->string('file_path', 500);
            $t->string('file_name', 255);
            $t->unsignedBigInteger('file_size')->default(0);

            $t->text('catatan')->nullable();
            $t->string('kesesuaian_isi', 20)->nullable();

            $t->timestamps();

            /* Dipakai oleh Pjp::belumLaporanBulananBulanIni(), yang
               menyaring jenis lalu tahun-bulan created_at. */
            $t->index(['pjp_id', 'jenis', 'created_at']);
        });

        /* ═══════════════════ EVALUASI SEMESTERAN ═══════════════════ */
        Schema::create('pjp_evaluasi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pjp_id')->constrained('pjp_perusahaan')->cascadeOnDelete();

            $t->unsignedSmallInteger('tahun');
            $t->unsignedTinyInteger('semester');

            $t->unsignedTinyInteger('skor_teknis');
            $t->unsignedTinyInteger('skor_keselamatan_kesehatan');
            $t->unsignedTinyInteger('skor_lingkungan');

            $t->text('catatan')->nullable();
            $t->timestamps();

            /* Satu penilaian per semester, bukan riwayat revisi.
               Penyimpanan ulang menimpa (updateOrCreate) supaya
               grafik tren tidak menampilkan dua titik pada satu
               semester dengan nilai yang berselisih. */
            $t->unique(['pjp_id', 'tahun', 'semester']);
        });

        /* ═══════════════════ DAFTAR PERIKSA PRAKUALIFIKASI ═══════════════════
         *
         * Kategori dan butirnya TIDAK berkolom company_id, sama seperti
         * master Investigasi: isinya daftar periksa resmi yang berlaku
         * sama bagi setiap pemegang IUP. Menyalinnya per perusahaan
         * berarti revisi lampiran regulasi harus dijalankan sebanyak
         * jumlah perusahaan, dan yang tertinggal akan menghitung skor
         * dengan bobot lama tanpa seorang pun tahu.
         */
        Schema::create('pjp_smkp_kategori', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 20)->unique();
            $t->string('nama', 200);

            /* Bobot kategori menurut lampiran. Kategori LEGALITAS punya
               bobot 4 di lampirannya, TETAPI tidak ikut dijumlahkan ke
               skor 178 — ia syarat wajib terpisah yang dijawab Y/T,
               bukan dinilai 0–3. Lihat Pjp::smkpCategoryBreakdown(). */
            $t->unsignedInteger('bobot');
            $t->unsignedInteger('urutan');
            $t->timestamps();
        });

        Schema::create('pjp_smkp_item', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kategori_id')->constrained('pjp_smkp_kategori')->cascadeOnDelete();

            $t->string('grup_kode', 20)->nullable();
            $t->string('grup_nama', 200)->nullable();

            $t->unsignedInteger('nomor');
            $t->text('pertanyaan');
            $t->text('petunjuk')->nullable();

            $t->unsignedInteger('bobot');
            $t->unsignedInteger('urutan');
            $t->timestamps();
        });

        Schema::create('pjp_smkp_jawaban', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pjp_id')->constrained('pjp_perusahaan')->cascadeOnDelete();
            $t->foreignId('item_id')->constrained('pjp_smkp_item')->cascadeOnDelete();

            /* ya | tidak | na — dipakai kategori LEGALITAS. */
            $t->string('jawaban', 10)->nullable();

            /* 0 | 1 | 2 | 3 | na — dipakai kategori A–P. Disimpan
               sebagai teks, bukan angka, justru karena 'na' bukan
               angka: 'na' berarti butirnya dikeluarkan dari penyebut,
               sedangkan 0 berarti dinilai dan tidak memenuhi. Menyimpan
               'na' sebagai NULL menyamakan "tidak berlaku" dengan
               "belum diisi", dan keduanya berlawanan akibatnya pada
               skor. */
            $t->string('nilai', 10)->nullable();

            $t->text('penjelasan')->nullable();
            $t->timestamps();

            $t->unique(['pjp_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pjp_smkp_jawaban');
        Schema::dropIfExists('pjp_smkp_item');
        Schema::dropIfExists('pjp_smkp_kategori');
        Schema::dropIfExists('pjp_evaluasi');
        Schema::dropIfExists('pjp_laporan');
        Schema::dropIfExists('pjp_perusahaan');
    }
};
