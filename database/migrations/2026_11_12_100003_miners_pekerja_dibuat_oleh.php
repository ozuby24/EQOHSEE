<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pendaftar manpower punya kolomnya sendiri: `mnr_pekerja.dibuat_oleh`.
 *
 * `mnr_pekerja.user_id` adalah tautan layanan mandiri HR — akun milik
 * pekerja itu sendiri, unik (lihat 2026_09_18_000001 dan Hr\Ess). Selama
 * ini pendaftarnya juga ditulis ke sana, sehingga pengguna yang
 * mendaftarkan pekerja kedua tertolak indeks unik (HTTP 500), dan
 * pekerja yang akunnya ditautkan dianggap "pendaftar" data manpower-nya
 * sendiri.
 *
 * Tanpa isi ulang: baris lama tidak dapat dibedakan antara tautan ESS
 * dan pendaftar, dan pekerja yang sudah punya dokumen tetap terjangkau
 * lewat aturan pengaju dokumennya. Hanya menambah kolom.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('mnr_pekerja', 'dibuat_oleh')) return;

        Schema::table('mnr_pekerja', function (Blueprint $t) {
            $t->foreignId('dibuat_oleh')->nullable()->index()
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('mnr_pekerja', 'dibuat_oleh')) return;

        Schema::table('mnr_pekerja', function (Blueprint $t) {
            $t->dropForeign(['dibuat_oleh']);
            $t->dropIndex(['dibuat_oleh']);
            $t->dropColumn('dibuat_oleh');
        });
    }
};
