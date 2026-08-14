<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verifikasi penutupan perintah kerja.
 *
 * Menutup sebuah perintah kerja menuliskan angka ke MTTR, ketersediaan,
 * dan biaya per ton. Selama penutupnya sendiri yang menyatakan selesai,
 * angka keandalan armada bersifat swa-lapor — persis keadaan yang dulu
 * ditutup pada data produksi.
 *
 * Yang TIDAK dilakukan di sini: mengeluarkan perintah kerja yang belum
 * diverifikasi dari hitungan. Pada data produksi, mengeluarkan yang
 * belum disetujui membuat capaian terlihat lebih kecil — pihak yang
 * lalai melapor dirugikan, dan itu benar. Pada waktu henti akibatnya
 * terbalik: mengeluarkannya membuat ketersediaan terlihat lebih BAGUS
 * persis ketika verifikasinya paling tertinggal. Alat yang rusak tetap
 * rusak walau belum ada yang menandatangani laporannya.
 *
 * Karena itu waktu henti selalu ikut dihitung, dan yang dilaporkan
 * adalah seberapa besar bagian angka yang belum diverifikasi. Yang
 * dikunci oleh verifikasi hanyalah penyuntingannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('work_orders', function (Blueprint $t) {
            $t->foreignId('ditutup_oleh')->nullable()->after('status')
                ->constrained('users')->nullOnDelete();
            $t->foreignId('diverifikasi_oleh')->nullable()->after('ditutup_oleh')
                ->constrained('users')->nullOnDelete();
            $t->timestamp('diverifikasi_pada')->nullable()->after('diverifikasi_oleh');
        });

        Schema::table('work_orders', function (Blueprint $t) {
            $t->index(['status', 'diverifikasi_pada'], 'work_orders_verifikasi_idx');
        });
    }

    public function down(): void
    {
        Schema::table('work_orders', function (Blueprint $t) {
            $t->dropIndex('work_orders_verifikasi_idx');
            $t->dropConstrainedForeignId('ditutup_oleh');
            $t->dropConstrainedForeignId('diverifikasi_oleh');
            $t->dropColumn('diverifikasi_pada');
        });
    }
};
