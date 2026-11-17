<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Laporan bahaya dari mode lapangan.
 *
 * kemungkinan × keparahan (1–5) adalah matriks 5×5 yang dipilih pelapor;
 * `risiko` tetap Rendah/Sedang/Tinggi dan DITURUNKAN dari skornya, supaya
 * KPI, analitik, dan ekspor yang membaca tiga tingkat itu tetap sejalan.
 * Laporan lama tanpa matriks tetap sah — kedua kolomnya boleh kosong.
 *
 * klien_id: pengenal yang dibuat perangkat saat laporan disusun. Laporan
 * yang tersimpan luring dapat terkirim dua kali (sinyal putus tepat saat
 * jawaban server datang); kunci unik per pelapor membuat kiriman kedua
 * menunjuk laporan yang sama, bukan membuat salinannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hazard_reports', function (Blueprint $t) {
            $t->unsignedTinyInteger('kemungkinan')->nullable()->after('risiko');
            $t->unsignedTinyInteger('keparahan')->nullable()->after('kemungkinan');
            $t->decimal('lat', 10, 7)->nullable()->after('lokasi');
            $t->decimal('lng', 10, 7)->nullable()->after('lat');
            $t->unsignedSmallInteger('akurasi_m')->nullable()->after('lng');
            $t->string('klien_id', 40)->nullable()->after('kode');
            $t->unique(['user_id', 'klien_id']);
        });
    }

    public function down(): void
    {
        Schema::table('hazard_reports', function (Blueprint $t) {
            $t->dropUnique(['user_id', 'klien_id']);
            $t->dropColumn(['kemungkinan', 'keparahan', 'lat', 'lng', 'akurasi_m', 'klien_id']);
        });
    }
};
