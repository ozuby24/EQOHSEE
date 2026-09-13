<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seragamkan ejaan status pendaftaran kursus.
 *
 * Aplikasi menulis dan membaca 'ongoing' dan 'finished' — lihat
 * LearnController::simpanKemajuan dan penyaring di CertificateController,
 * EvaluationController, dan DashboardController. Data contoh sempat
 * menulis 'in_progress' dan 'completed', ejaan yang tidak satu baris
 * kode pun membacanya.
 *
 * Barisnya tetap tersimpan dan tetap tampil di daftar; yang salah hanya
 * angkanya. "Kursus Selesai 0" pada peserta yang seluruh modulnya sudah
 * tuntas, sertifikat yang tidak pernah dapat diklaim, dan tombol
 * "Lanjutkan Pembelajaran" yang membuka kursus sembarang.
 *
 * Turun memulangkan ejaan lamanya apa adanya — bukan karena ejaan itu
 * benar, melainkan supaya migrasi ini dapat dibatalkan tanpa menebak
 * baris mana yang sejak awal sudah benar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('enrollments')) return;

        DB::table('enrollments')->where('status', 'completed')->update(['status' => 'finished']);
        DB::table('enrollments')->where('status', 'in_progress')->update(['status' => 'ongoing']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('enrollments')) return;

        DB::table('enrollments')->where('status', 'finished')->update(['status' => 'completed']);
        DB::table('enrollments')->where('status', 'ongoing')->update(['status' => 'in_progress']);
    }
};
