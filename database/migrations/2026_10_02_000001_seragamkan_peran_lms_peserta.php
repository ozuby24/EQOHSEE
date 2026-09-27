<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Peran LMS "peserta" diseragamkan menjadi "trainee".
 *
 * Nilai sah kolom `lms_role` adalah trainee, trainer, dan ktt — itu yang
 * diterima formulir pengguna (Admin\UserController) dan yang dipasang
 * pendaftaran. Data contoh sempat menulis "peserta", nilai yang tidak
 * dikenal di mana pun: pemilih peran di formulir admin tampil kosong untuk
 * akun itu, dan hitungan "Peserta" di Pusat Kendali Sistem menunjukkan nol
 * padahal belasan akun memang peserta.
 *
 * Aman diulang dan tidak dapat merusak apa pun: hanya baris bernilai
 * "peserta" yang disentuh.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->where('lms_role', 'peserta')->update(['lms_role' => 'trainee']);
    }

    public function down(): void
    {
        // Tidak dikembalikan: "peserta" memang bukan nilai yang sah.
    }
};
