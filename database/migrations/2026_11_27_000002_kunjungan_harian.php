<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hitungan pengunjung situs per hari — anonim.
 *
 * Satu baris per tanggal berisi satu angka. Tidak ada alamat IP, tidak ada
 * agen peramban, tidak ada pengenal orang: yang menandai "peramban ini
 * sudah dihitung hari ini" hanya kuki fungsional berisi tanggal di
 * perambannya sendiri (App\Http\Middleware\CatatPengunjung).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunjungan_harian', function (Blueprint $t) {
            $t->id();
            $t->date('tanggal')->unique();
            $t->unsignedInteger('pengunjung')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunjungan_harian');
    }
};
