<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Penanda "sudah melihat pengenalan".
 *
 * Kolomnya menyimpan KAPAN, bukan sekadar sudah atau belum. Boolean
 * hanya menjawab pertanyaan hari ini; tanggalnya menjawab juga
 * pertanyaan yang pasti datang kemudian — berapa lama orang baru
 * menunda membaca pengenalannya, dan apakah yang mendaftar pekan lalu
 * sempat melihat versi yang sudah diperbaiki.
 *
 * ── Kenapa akun lama diisi mundur ──
 *
 * Dibiarkan null, seluruh pengguna yang sudah bekerja berbulan-bulan di
 * situs ini akan disambut "Selamat datang di EQOHSEE" pada pembukaan
 * halaman berikutnya, lengkap dengan pengenalan modul yang sudah mereka
 * pakai tiap hari. Sambutan yang datang terlambat berbulan-bulan tidak
 * membantu siapa pun; ia hanya menghalangi pekerjaan yang sedang
 * dikerjakan.
 *
 * Maka seluruh akun yang sudah ada ditandai selesai pada saat migrasi
 * ini berjalan, dan yang melihat pengenalannya hanya akun yang BARU
 * mendaftar sesudahnya. Yang terlanjur menutup pun tidak kehilangan
 * apa-apa: pengenalannya tetap dapat dibuka lagi lewat menu akun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('tur_selesai_pada')->nullable()->after('masuk_terakhir_ip');
        });

        /* Satu pernyataan untuk seluruh baris, bukan satu per pengguna:
           pada basis data produksi jumlahnya ribuan, dan migrasi yang
           mengirim ribuan UPDATE menahan deploy jauh lebih lama daripada
           yang pantas untuk mengisi satu kolom. */
        DB::table('users')->update(['tur_selesai_pada' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('tur_selesai_pada');
        });
    }
};
