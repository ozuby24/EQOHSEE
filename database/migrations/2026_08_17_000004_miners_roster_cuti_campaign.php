<?php

use App\Support\Alur;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiga isi Miners yang belum ada: field break, cuti tahunan, campaign.
 *
 * Ketiganya mengikuti D'Best, dan ketiganya memang tentang orang yang
 * sama — karena itu tempatnya di sini, bukan modul tersendiri.
 *
 * FIELD BREAK BUKAN CUTI, dan memisahkannya disengaja meskipun keduanya
 * berarti orangnya tidak ada di lokasi. Field break adalah bagian dari
 * pola kerja rotasi: delapan minggu di lokasi, dua minggu pulang, dan
 * jatahnya bukan hak yang dipakai melainkan giliran yang datang. Cuti
 * tahunan adalah hak dengan jatah yang berkurang. Menyatukannya membuat
 * jatah cuti seseorang habis hanya karena rosternya berjalan normal.
 *
 * KEDUANYA BUKAN KETIDAKLAYAKAN. Orang yang sedang field break tetap
 * memenuhi syarat masuk — ia hanya sedang tidak di sini. Mencampurnya
 * ke dalam hitungan kelayakan akan membuat daftar "tidak boleh bekerja"
 * berisi puluhan nama yang sebenarnya tidak bermasalah sama sekali, dan
 * daftar semacam itu berhenti dibaca dalam seminggu.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ── field break: giliran pulang pada pola kerja rotasi ── */
        Schema::create('miners_field_break', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paspor_id')->constrained('paspor')->cascadeOnDelete();

            $t->string('pola')->nullable();          // mis. "8:2", "10:4"
            $t->date('mulai');
            $t->date('selesai');
            $t->date('kembali_aktual')->nullable();  // kapan benar-benar kembali

            $t->string('jenis')->default('Roster');  // Roster | Darurat | Pengganti
            $t->string('lokasi_tujuan')->nullable();

            /* Siapa yang menggantikan selama ia pergi. Kosong bukan
               berarti tidak ada penggantinya — kadang pekerjaannya
               memang berhenti — jadi kolomnya nullable dan tidak
               diwajibkan. */
            $t->foreignId('pengganti_id')->nullable()
              ->constrained('paspor')->nullOnDelete();

            $t->text('catatan')->nullable();

            $t->string('status')->default(Alur::DRAF);
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->timestamps();

            $t->index(['paspor_id', 'mulai']);
            $t->index(['mulai', 'selesai']);
        });

        /* ── jatah cuti tahunan, per orang per tahun ── */
        Schema::create('miners_cuti_jatah', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paspor_id')->constrained('paspor')->cascadeOnDelete();

            $t->unsignedSmallInteger('tahun');
            $t->unsignedSmallInteger('jatah')->default(12);

            /* Sisa tahun lalu yang boleh dibawa. Dipisah dari jatahnya
               supaya terlihat berapa yang memang hak tahun ini dan
               berapa yang warisan — dua angka yang aturan hangusnya
               berbeda. */
            $t->unsignedSmallInteger('bawaan')->default(0);

            $t->text('catatan')->nullable();
            $t->timestamps();

            /* Satu orang satu baris per tahun. Tanpa ini, jatah ganda
               tidak menimbulkan galat — ia hanya melipatgandakan hak
               cuti seseorang secara diam-diam. */
            $t->unique(['paspor_id', 'tahun']);
        });

        /* ── pengajuan cuti ── */
        Schema::create('miners_cuti', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paspor_id')->constrained('paspor')->cascadeOnDelete();

            $t->unsignedSmallInteger('tahun');       // jatah tahun mana yang dipakai
            $t->string('jenis')->default('Tahunan'); // Tahunan | Sakit | Melahirkan | Penting | Tanpa Gaji
            $t->date('mulai');
            $t->date('selesai');

            /* Jumlah hari DISIMPAN, tidak dihitung ulang dari tanggalnya
               setiap kali dibaca.

               Sengaja begitu: yang menentukan berapa hari terpotong dari
               jatah adalah keputusan saat cuti disetujui — termasuk bila
               ada hari libur di tengahnya yang tidak dihitung. Menghitung
               ulang dari selisih tanggal akan diam-diam mengubah sisa
               cuti orang yang cutinya sudah lama disetujui, setiap kali
               aturan hari liburnya diubah. */
            $t->unsignedSmallInteger('jumlah_hari');

            $t->string('alamat_cuti')->nullable();
            $t->string('kontak')->nullable();
            $t->foreignId('pengganti_id')->nullable()
              ->constrained('paspor')->nullOnDelete();

            $t->text('alasan')->nullable();

            $t->string('status')->default(Alur::DRAF);
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->timestamps();

            $t->index(['paspor_id', 'tahun']);
            $t->index(['mulai', 'selesai']);
            $t->index('status');
        });

        /* ── campaign keselamatan ── */
        Schema::create('miners_campaign', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('judul');
            $t->string('jenis')->default('Poster');   // Poster | Artikel | Video | Toolbox | Spanduk
            $t->string('tema')->nullable();

            $t->date('mulai');
            $t->date('selesai')->nullable();

            $t->string('sasaran')->nullable();        // departemen / area yang dituju
            $t->text('ringkasan')->nullable();
            $t->longText('isi')->nullable();
            $t->string('berkas')->nullable();

            /* Jangkauan: berapa orang yang sudah menerimanya. Diisi
               tangan, dan sengaja tidak dihitung otomatis dari jumlah
               pekerja — campaign yang dipasang di satu pos gerbang tidak
               menjangkau seluruh perusahaan, dan angka yang dikarang
               sistem lebih buruk daripada angka yang kosong. */
            $t->unsignedInteger('jangkauan')->nullable();

            $t->string('status')->default(Alur::DRAF);
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->timestamps();

            $t->index(['company_id', 'status']);
            $t->index(['mulai', 'selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('miners_campaign');
        Schema::dropIfExists('miners_cuti');
        Schema::dropIfExists('miners_cuti_jatah');
        Schema::dropIfExists('miners_field_break');
    }
};
