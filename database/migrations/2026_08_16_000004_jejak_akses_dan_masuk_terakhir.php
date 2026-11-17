<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jejak akses: dari mana, dengan apa, dan atas nama perusahaan mana.
 *
 * Sebelum ini `activity_log` mencatat SIAPA dan APA, tetapi tidak
 * pernah DARI MANA. Pada satu pemasangan yang dipakai bersama beberapa
 * perusahaan, itu membuat seluruh catatannya kehilangan gunanya justru
 * pada saat ia paling dibutuhkan: ketika ada yang bertanya "apakah ini
 * benar orangnya, atau ada yang memakai akunnya".
 *
 * Tiga kolom ditambahkan ke catatan yang SUDAH ADA, bukan ke tabel
 * baru. Alasannya sengaja: seluruh modul — inspeksi, izin kerja,
 * peledakan, gudang — sudah memanggil ActivityLog::write. Menambah
 * tabel kedua berarti separuh jejaknya beralamat dan separuh lagi
 * tidak, dan yang memeriksanya harus tahu lebih dulu separuh yang mana.
 *
 * `company_id` boleh kosong. Peristiwa masuk yang GAGAL tidak punya
 * perusahaan — bahkan sering tidak punya pengguna — dan justru
 * peristiwa itulah yang paling perlu dicatat.
 *
 * Pada `users`, dua kolom penanda masuk terakhir. Ini bukan duplikasi
 * dari jejaknya: jejak dipangkas berkala, sedangkan pertanyaan "akun
 * mana yang sudah setahun tidak dipakai" harus tetap terjawab sesudah
 * pemangkasan. Akun tidur adalah pintu yang tidak dijaga siapa pun
 * karena tidak ada yang merasa memilikinya lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $t) {
            $t->string('ip', 45)->nullable()->after('detail');          // 45 = muat IPv6
            $t->string('user_agent', 255)->nullable()->after('ip');
            $t->unsignedBigInteger('company_id')->nullable()->after('user_agent');
        });

        Schema::table('activity_log', function (Blueprint $t) {
            /* Dua kueri yang akan sering dijalankan halaman keamanan:
               "peristiwa modul keamanan sejak sekian" dan "peristiwa
               perusahaan ini". Tanpa indeks keduanya memindai seluruh
               tabel, dan tabel inilah yang paling cepat tumbuh. */
            $t->index(['module', 'created_at'], 'activity_log_module_waktu_idx');
            $t->index(['company_id', 'created_at'], 'activity_log_company_waktu_idx');
        });

        Schema::table('users', function (Blueprint $t) {
            $t->timestamp('masuk_terakhir_at')->nullable()->after('active');
            $t->string('masuk_terakhir_ip', 45)->nullable()->after('masuk_terakhir_at');
        });

        /* Tabel `sessions` sengaja tidak disentuh. Daftar perangkat
           aktif membacanya per pengguna, dan indeks yang diperlukan
           untuk itu — sessions_user_id_index — sudah dibuat oleh
           migrasi bawaan Laravel. Menambahnya lagi hanya melahirkan
           indeks kembar yang harus ikut diperbarui setiap kali ada
           yang masuk. */
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $t) {
            $t->dropIndex('activity_log_module_waktu_idx');
            $t->dropIndex('activity_log_company_waktu_idx');
            $t->dropColumn(['ip', 'user_agent', 'company_id']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['masuk_terakhir_at', 'masuk_terakhir_ip']);
        });
    }
};
