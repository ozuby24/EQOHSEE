<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda perusahaan contoh.
 *
 * Pemuat data contoh menghapus lalu mengisi ulang seluruh data satu
 * perusahaan. Itu tindakan yang tidak dapat dibatalkan, dan satu-satunya
 * hal yang memisahkannya dari kehilangan data sungguhan adalah
 * ketepatan memilih perusahaan mana.
 *
 * Karena itu penjaganya tidak diletakkan pada tampilan — tombol yang
 * meminta konfirmasi tetap dapat ditekan pada perusahaan yang salah.
 * Penjaganya diletakkan pada datanya: hanya perusahaan yang ditandai
 * `demo` yang dapat dimuati, dan penandaan itu tindakan tersendiri yang
 * harus disengaja lebih dulu.
 *
 * Bawaannya false. Perusahaan yang sudah ada tidak akan pernah menjadi
 * sasaran pemuat ini kecuali seseorang menandainya secara sadar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->boolean('demo')->default(false)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->dropColumn('demo');
        });
    }
};
