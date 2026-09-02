<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Membuang Field Break, Cuti Tahunan, dan Campaign dari Miners.
 *
 * ── MENGAPA MIGRASI BARU, BUKAN MENYUNTING YANG LAMA ──
 *
 * Migrasi 2026_08_17_000004 yang membuat keempat tabel ini sudah
 * dijalankan di server. Menyuntingnya di tempat hanya berpengaruh pada
 * basis data yang dibangun dari nol; yang sudah berjalan tetap memiliki
 * tabelnya, sebab Laravel tidak menjalankan ulang migrasi yang sudah
 * tercatat. Akibatnya dua basis data yang mengaku versi sama punya
 * skema berbeda — dan selisih itu tidak menimbulkan galat sampai ada
 * yang menulis kueri yang menyentuh tabelnya.
 *
 * ── MENGAPA TIDAK DAPAT DIKEMBALIKAN UTUH ──
 *
 * `down()` membangun ulang tabelnya supaya migrasinya dapat dimundurkan,
 * tetapi ISINYA tidak kembali. Itu memang sifat penghapusan, dan
 * disebutkan di sini supaya tidak ada yang mengira `migrate:rollback`
 * memulihkan datanya. Yang memulihkan data hanya cadangan basis data.
 *
 * Riwayat kodenya tetap ada di git — model, controller, dan halaman
 * Vue-nya dapat dipanggil kembali dari commit sebelum ini bila suatu
 * saat ketiganya diperlukan lagi.
 *
 * ── URUTAN PEMBUANGAN ──
 *
 * Keempatnya menunjuk `paspor`, bukan satu sama lain, jadi urutannya
 * bebas. Tetap ditulis eksplisit satu per satu — `dropIfExists` atas
 * tabel yang tidak ada tidak menimbulkan galat, sehingga migrasi ini
 * aman dijalankan pada basis data yang belum pernah memilikinya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('miners_campaign');
        Schema::dropIfExists('miners_cuti');
        Schema::dropIfExists('miners_cuti_jatah');
        Schema::dropIfExists('miners_field_break');
    }

    public function down(): void
    {
        Schema::create('miners_field_break', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('paspor_id')->constrained('paspor')->cascadeOnDelete();
            $t->string('pola', 30)->nullable();
            $t->date('mulai');
            $t->date('selesai');
            $t->date('kembali_aktual')->nullable();
            $t->foreignId('pengganti_id')->nullable()->constrained('paspor')->nullOnDelete();
            $t->string('catatan', 500)->nullable();
            $t->string('status', 20)->default('draf');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->string('alasan_tolak', 500)->nullable();
            $t->timestamps();

            $t->index(['company_id', 'mulai']);
        });

        Schema::create('miners_cuti_jatah', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('paspor_id')->constrained('paspor')->cascadeOnDelete();
            $t->integer('tahun');
            $t->integer('jatah_hari')->default(12);
            $t->integer('sisa_awal')->default(0);
            $t->timestamps();

            $t->unique(['paspor_id', 'tahun']);
        });

        Schema::create('miners_cuti', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('paspor_id')->constrained('paspor')->cascadeOnDelete();
            $t->string('jenis', 30);
            $t->date('mulai');
            $t->date('selesai');
            $t->integer('jumlah_hari')->default(0);
            $t->string('keperluan', 500)->nullable();
            $t->string('status', 20)->default('draf');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->string('alasan_tolak', 500)->nullable();
            $t->timestamps();

            $t->index(['company_id', 'mulai']);
        });

        Schema::create('miners_campaign', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('judul', 200);
            $t->string('jenis', 30);
            $t->string('tema', 120)->nullable();
            $t->date('mulai');
            $t->date('selesai')->nullable();
            $t->string('sasaran', 150)->nullable();
            $t->string('ringkasan', 1000)->nullable();
            $t->text('isi')->nullable();
            $t->integer('jangkauan')->default(0);
            $t->string('status', 20)->default('draf');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->string('alasan_tolak', 500)->nullable();
            $t->timestamps();

            $t->index(['company_id', 'mulai']);
        });
    }
};
