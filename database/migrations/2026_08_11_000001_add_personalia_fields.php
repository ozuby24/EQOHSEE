<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom pendukung modul Personalia.
 *
 * Warna tema disimpan, bukan dihitung ulang tiap permintaan: membaca
 * berkas logo dan mencacah pikselnya pada setiap pemuatan halaman membuat
 * seluruh aplikasi menunggu pekerjaan yang hasilnya tidak pernah berubah
 * sampai logonya diganti.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            if (!Schema::hasColumn('companies', 'theme_color')) {
                // Hex 6 digit beserta pagarnya.
                $t->string('theme_color', 7)->nullable()->after('logo');
            }
            if (!Schema::hasColumn('companies', 'theme_dark')) {
                $t->string('theme_dark', 7)->nullable()->after('theme_color');
            }
        });

        Schema::table('users', function (Blueprint $t) {
            if (!Schema::hasColumn('users', 'tema')) {
                // 'terang', 'gelap', atau null = mengikuti setelan perangkat.
                $t->string('tema', 10)->nullable()->after('avatar');
            }
            if (!Schema::hasColumn('users', 'bio')) {
                $t->string('bio', 300)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('users', 'whatsapp')) {
                $t->string('whatsapp', 32)->nullable()->after('phone');
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->dropColumn(['theme_color', 'theme_dark']);
        });

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['tema', 'bio', 'whatsapp']);
        });
    }
};
