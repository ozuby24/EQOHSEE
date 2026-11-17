<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berita melekat pada perusahaan yang menerbitkannya.
 *
 * Sebelum ini tabel berita tidak punya kolom perusahaan sama sekali,
 * sehingga satu pengumuman terbaca oleh seluruh perusahaan pada
 * pemasangan yang sama. Untuk pustaka pelatihan — kursus, kuis,
 * prosedur, template inspeksi — berbagi memang disengaja: itulah
 * barang yang dijual. Pengumuman bukan; ia menyebut nama orang,
 * jadwal, dan kejadian di satu lokasi kerja.
 *
 * Kolomnya BOLEH KOSONG, dan itu keputusan yang menentukan. Scope
 * MilikPerusahaan memperlakukan baris tanpa perusahaan sebagai milik
 * bersama, sehingga:
 *
 *   - Berita yang sudah ada tetap terbaca semua orang. Ia memang
 *     ditulis ketika belum ada pemisahan, dan menebak pemiliknya
 *     sekarang berarti menyembunyikannya dari yang berhak.
 *   - Berita baru mewarisi perusahaan penulisnya lewat
 *     BerpemilikPerusahaan.
 *   - Pengumuman se-pemasangan tetap mungkin: cukup dibuat oleh
 *     administrator yang tidak terikat perusahaan.
 *
 * Karena itu tidak ada backfill di sini. Backfill yang salah pada
 * tabel ini tidak menimbulkan galat — ia hanya membuat pengumuman
 * lenyap dari orang yang seharusnya membacanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $t) {
            $t->unsignedBigInteger('company_id')->nullable()->after('id');
            $t->index(['company_id', 'published_at'], 'news_company_terbit_idx');
        });
    }

    public function down(): void
    {
        Schema::table('news', function (Blueprint $t) {
            $t->dropIndex('news_company_terbit_idx');
            $t->dropColumn('company_id');
        });
    }
};
