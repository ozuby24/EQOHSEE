<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom `jenis` lampiran permit: 40 → 100 aksara.
 *
 * ── Cacatnya nyata, dan bukan hanya pada data contoh ──
 *
 * Kunci lampiran dibentuk dari kalimat daftar periksa SOP, bukan diketik
 * pendek-pendek. Str::slug('Hasil MCU dari klinik, dinyatakan Fit untuk
 * bekerja') menghasilkan 50 aksara; yang terpanjang, 'Sertifikat refresh
 * kompetensi (skill up) dengan nilai kelulusan', menjadi 61. Dari
 * Acuan::BERKAS_WAJIB, DUA PULUH ENAM kunci melampaui batas 40 itu.
 *
 * Pengendalinya sendiri sudah memvalidasi `max:100`. Jadi bukan hanya
 * pemuat data contoh yang gagal — siapa pun yang mengunggah lampiran
 * dengan nama panjang pada permit sungguhan menabrak batas yang sama,
 * dan yang ia lihat adalah galat SQL mentah di tengah halaman.
 *
 * ── Kenapa tidak ketahuan lebih awal ──
 *
 * SQLite tidak menegakkan panjang VARCHAR sama sekali; ia menyimpan
 * enam puluh satu aksara ke kolom empat puluh tanpa berkata apa-apa.
 * MySQL menolaknya dengan SQLSTATE[22001]. Seluruh uji berjalan di
 * SQLite, jadi tidak satu pun yang merah — persis pola yang sama dengan
 * kolom `luas_ha` dulu, dan itulah sebabnya uji LebarKolomTest
 * ditambahkan bersama migrasi ini: supaya yang berikutnya ketahuan
 * sebelum sampai ke produksi.
 *
 * 100 dipilih agar cocok dengan aturan validasi yang sudah ada, bukan
 * angka yang kebetulan cukup hari ini. Indeks uniknya tetap aman:
 * bigint 8 bita + varchar(100) utf8mb4 400 bita = 408, jauh di bawah
 * batas 3072 bita milik InnoDB.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mnr_permit_berkas', function (Blueprint $t) {
            $t->string('jenis', 100)->change();
        });
    }

    public function down(): void
    {
        /* PERINGATAN: ini dapat membuang data.
         *
         * Memendekkannya kembali memotong kunci yang sudah tersimpan, dan
         * potongan itu dapat menabrak indeks unik (permit_id, jenis) —
         * dua lampiran berbeda yang empat puluh aksara pertamanya sama
         * menjadi satu baris. Dibiarkan gagal apa adanya, bukan
         * "dibereskan" diam-diam: MySQL akan menolaknya, dan penolakan
         * yang terlihat jauh lebih baik daripada lampiran yang hilang
         * tanpa ada yang tahu.
         *
         * Bila memang perlu turun, bereskan barisnya lebih dulu dengan
         * tangan, sesudah memutuskan lampiran mana yang dipertahankan. */
        Schema::table('mnr_permit_berkas', function (Blueprint $t) {
            $t->string('jenis', 40)->change();
        });
    }
};
