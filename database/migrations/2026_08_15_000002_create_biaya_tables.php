<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengendalian biaya operasi.
 *
 * Yang TIDAK ada di sini pantas disebut lebih dulu: tidak ada tabel
 * produksi. Tonase dan overburden diambil dari catatan Mine Operations
 * yang sudah disetujui. Angka produksi yang diketik dua kali di dua
 * modul akan berselisih cepat atau lambat, dan biaya per ton dengan
 * denominator yang salah terlihat persis seperti biaya per ton yang
 * benar — tidak ada galat, hanya keputusan yang diambil di atas angka
 * yang keliru.
 *
 * Bagan akun berdiri sebagai data, bukan daftar di dalam kode. Susunan
 * akun biaya mengikuti RKAB dan kebiasaan tiap perusahaan, dan berganti
 * mengikuti tahun anggarannya; menanamkannya di kode berarti setiap
 * perubahan RKAB menuntut penerbitan aplikasi baru.
 *
 * Anggaran disimpan tahunan, bukan bulanan, dan itu disengaja.
 * Membagi dua belas menghasilkan anggaran bulanan yang terlihat rapi
 * padahal tidak pernah disusun siapa pun — lalu setiap bulan yang
 * pekerjaannya memang lebih berat terbaca sebagai pemborosan. Laju
 * penyerapan di modul ini diukur terhadap kemajuan produksi, sebab
 * kalender tidak menghasilkan ton.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bagan akun biaya.
        Schema::create('biaya_akuns', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('kode');
            $t->string('nama');
            $t->string('kelompok')->default('lain');
            $t->string('jenis')->default('variabel');   // tetap, variabel

            // Satuan mengaktifkan pemecahan harga terhadap pemakaian.
            // Akun tanpa satuan — sewa, upah borongan — tetap dapat
            // dianggarkan, hanya selisihnya yang berhenti pada satu angka.
            $t->string('satuan')->nullable();           // liter, ban, kg, jam

            $t->boolean('aktif')->default(true);
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'kode']);
            $t->index(['company_id', 'kelompok']);
        });

        // Anggaran tahunan per akun per pusat biaya.
        Schema::create('biaya_anggarans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('biaya_akun_id')->constrained()->cascadeOnDelete();

            $t->unsignedSmallInteger('tahun');
            $t->string('pusat_biaya')->default('penambangan');
            $t->decimal('nilai_rp', 18, 2)->default(0);

            // Kuantitas rencana memberi harga rencana lewat pembagian.
            // Harganya tidak ikut disimpan: angka turunan yang disimpan
            // cepat atau lambat berselisih dengan sumbernya, dan tidak
            // ada cara bagi pembacanya untuk tahu mana yang benar.
            $t->decimal('kuantitas_rencana', 18, 3)->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'tahun', 'biaya_akun_id', 'pusat_biaya'], 'biaya_anggaran_unik');
            $t->index(['company_id', 'tahun']);
        });

        // Realisasi bulanan.
        Schema::create('biaya_realisasis', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('biaya_akun_id')->constrained()->cascadeOnDelete();

            $t->unsignedSmallInteger('tahun');
            $t->unsignedTinyInteger('bulan');
            $t->string('pusat_biaya')->default('penambangan');
            $t->decimal('nilai_rp', 18, 2)->default(0);
            $t->decimal('kuantitas', 18, 3)->nullable();

            $t->string('status')->default('draf');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'tahun', 'bulan', 'biaya_akun_id', 'pusat_biaya'], 'biaya_realisasi_unik');
            $t->index(['company_id', 'tahun', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('biaya_realisasis');
        Schema::dropIfExists('biaya_anggarans');
        Schema::dropIfExists('biaya_akuns');
    }
};
