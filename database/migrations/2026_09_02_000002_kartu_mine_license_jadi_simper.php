<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menamai jenis kartu "Mine License" menjadi "SIMPER".
 *
 * ── MENGAPA PERLU MIGRASI, BUKAN SEKADAR MENGUBAH KONSTANTA ──
 *
 * `AlurMiner::KARTU_LICENSE` bukan label tampilan; nilainya TERSIMPAN
 * apa adanya di kolom `paspor_kartu.jenis`. Mengubah konstantanya saja
 * membuat kode mencari 'SIMPER' sementara seluruh baris lama masih
 * berbunyi 'Mine License' — dan yang terjadi bukan galat melainkan
 * daftar SIMPER yang kosong, kartu yang hilang dari halaman orangnya,
 * dan syarat alur yang menyimpulkan orang itu belum pernah punya
 * SIMPER sama sekali.
 *
 * Kegagalan semacam itu tidak memunculkan satu pun pesan. Karena itu
 * baris lamanya ikut diubah di sini, pada migrasi yang sama dengan
 * pergantian namanya.
 *
 * ── MENGAPA NAMANYA BERUBAH ──
 *
 * "Mine License" adalah sebutan yang hanya dipakai aplikasi ini.
 * Dokumen yang dimaksud disebut SIMPER di lapangan, di DBEST, dan di
 * Project1 — dan nama yang hanya dikenal aplikasinya sendiri memaksa
 * tiap orang baru menerjemahkannya sekali di kepala setiap kali
 * membacanya.
 *
 * ── NAMA RUTENYA TIDAK IKUT BERUBAH ──
 *
 * `miners.riwayat.mine-license` dan alamat `/miners/riwayat/mine-license`
 * dibiarkan. Keduanya tidak pernah terbaca pengguna, sementara
 * mengubahnya memutus setiap tautan yang sudah pernah dibagikan. Yang
 * dilihat orang — label menu, judul halaman, isi kolom jenis — seluruhnya
 * sudah berbunyi SIMPER.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('paspor_kartu')->where('jenis', 'Mine License')->update(['jenis' => 'SIMPER']);
    }

    public function down(): void
    {
        DB::table('paspor_kartu')->where('jenis', 'SIMPER')->update(['jenis' => 'Mine License']);
    }
};
