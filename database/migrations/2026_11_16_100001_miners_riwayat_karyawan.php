<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat Karyawan lintas perusahaan (mengikuti DBEST, 16 Nov 2026).
 *
 *   mnr_pekerja.sebab_nonaktif      kunci alasan (resign, habis_kontrak,
 *                                   phk_pelanggaran, …) — `alasan_nonaktif`
 *                                   yang sudah ada tetap menjadi keterangannya
 *   mnr_pekerja.rekomendasi_rekrut  layak | pertimbangkan | tidak
 *   mnr_pekerja.nonaktif_oleh       akun yang menonaktifkan
 *
 *   mnr_pelanggaran                 satu baris per catatan pelanggaran,
 *                                   ikut terhapus bila pekerjanya dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mnr_pekerja', function (Blueprint $t) {
            $t->string('sebab_nonaktif', 30)->nullable()->after('alasan_nonaktif');
            $t->string('rekomendasi_rekrut', 20)->nullable()->after('sebab_nonaktif');
            $t->foreignId('nonaktif_oleh')->nullable()->after('rekomendasi_rekrut')->constrained('users')->nullOnDelete();
        });

        Schema::create('mnr_pelanggaran', function (Blueprint $t) {
            $t->id();
            // Perusahaan pemilik data — perusahaan pekerja saat dicatat.
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();
            $t->date('tanggal');
            $t->string('kategori', 30);
            $t->string('tingkat', 10);
            $t->string('sanksi', 20)->nullable();
            $t->text('uraian');
            $t->string('lokasi', 150)->nullable();
            $t->string('no_dokumen', 100)->nullable();

            // diajukan | diverifikasi | ditolak
            $t->string('status', 15)->default('diajukan')->index();
            $t->text('catatan_verifikasi')->nullable();
            $t->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diverifikasi_pada')->nullable();

            $t->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->index(['pekerja_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mnr_pelanggaran');
        Schema::table('mnr_pekerja', function (Blueprint $t) {
            $t->dropConstrainedForeignId('nonaktif_oleh');
            $t->dropColumn(['sebab_nonaktif', 'rekomendasi_rekrut']);
        });
    }
};
