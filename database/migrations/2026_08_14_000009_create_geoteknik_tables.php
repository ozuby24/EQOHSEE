<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemantauan kestabilan lereng.
 *
 * Yang disimpan di sini adalah pengamatan lapangan dan rancangan yang
 * menjadi acuannya — bukan hasil kajian geoteknik. Faktor keamanan dan
 * probabilitas longsor rancangan diisikan sebagai angka dari kajian yang
 * sudah dibuat tenaga kompeten, lengkap dengan tanggal dan penyusunnya,
 * supaya jelas keputusan siapa yang sedang dipakai sebagai acuan.
 *
 * Geometri dicatat dua kali: sebagaimana dirancang dan sebagaimana
 * terbangun. Selisih keduanya adalah hal yang paling sering menjadi awal
 * masalah, dan satu-satunya bagian dari kestabilan lereng yang dapat
 * diperiksa tanpa keahlian geoteknik — cukup diukur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geo_lerengs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('kode');
            $t->string('nama');
            $t->string('jenis')->default('highwall');   // highwall, lowwall, sidewall, timbunan, stockpile
            $t->string('lokasi')->nullable();
            $t->string('litologi')->nullable();

            // Rancangan — dari kajian geoteknik.
            $t->decimal('tinggi_rencana_m', 8, 2)->nullable();
            $t->decimal('sudut_rencana_deg', 5, 2)->nullable();
            $t->decimal('tinggi_jenjang_rencana_m', 8, 2)->nullable();
            $t->decimal('lebar_berm_rencana_m', 8, 2)->nullable();

            // Terbangun — dari pengukuran lapangan.
            $t->decimal('tinggi_aktual_m', 8, 2)->nullable();
            $t->decimal('sudut_aktual_deg', 5, 2)->nullable();
            $t->decimal('tinggi_jenjang_aktual_m', 8, 2)->nullable();
            $t->decimal('lebar_berm_aktual_m', 8, 2)->nullable();

            // Acuan dari kajian, bukan hasil hitungan modul ini.
            $t->decimal('fk_rencana', 5, 2)->nullable();
            $t->decimal('ppa_rencana_persen', 5, 2)->nullable();
            $t->string('kajian_oleh')->nullable();
            $t->date('kajian_tanggal')->nullable();
            $t->unsignedSmallInteger('interval_kajian_hari')->nullable();

            // Ambang TARP khusus lereng ini. Kosong berarti memakai
            // bawaan App\Support\Kestabilan.
            $t->decimal('ambang_waspada_mm_hari', 8, 2)->nullable();
            $t->decimal('ambang_siaga_mm_hari', 8, 2)->nullable();
            $t->decimal('ambang_awas_mm_hari', 8, 2)->nullable();

            $t->string('status')->default('aktif');     // aktif, arsip
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'status']);
            $t->index('jenis');
        });

        Schema::create('geo_instrumens', function (Blueprint $t) {
            $t->id();
            $t->foreignId('geo_lereng_id')->constrained()->cascadeOnDelete();

            $t->string('kode');
            $t->string('jenis')->default('prisma');     // prisma, extensometer, piezometer, inklinometer, radar
            $t->string('status')->default('siap');      // siap, rusak, perawatan, arsip
            $t->decimal('elevasi_m', 8, 2)->nullable();
            $t->date('kalibrasi_terakhir')->nullable();
            $t->timestamps();

            $t->unique(['geo_lereng_id', 'kode']);
            $t->index('status');
        });

        Schema::create('geo_bacaans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('geo_lereng_id')->constrained()->cascadeOnDelete();
            $t->foreignId('geo_instrumen_id')->nullable()->constrained()->nullOnDelete();

            $t->date('tanggal');

            // Perpindahan kumulatif sejak titik nol instrumen, bukan
            // selisih harian. Lajunya diturunkan; menyimpan keduanya
            // membuka peluang keduanya tidak lagi sepakat.
            $t->decimal('perpindahan_mm', 12, 3)->default(0);

            $t->decimal('retakan_mm', 10, 3)->nullable();
            $t->decimal('muka_air_m', 8, 2)->nullable();
            $t->decimal('curah_hujan_mm', 8, 2)->nullable();
            $t->boolean('ada_gejala')->default(false);   // retakan baru, gugur batu, rembesan
            $t->text('gejala')->nullable();

            $t->string('status')->default('draf');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            // Satu instrumen satu bacaan per hari. Tanpa ini, dua bacaan
            // pada hari yang sama membuat laju terhitung dari selang nol.
            $t->unique(['geo_instrumen_id', 'tanggal']);
            $t->index(['status', 'company_id']);
            $t->index(['geo_lereng_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geo_bacaans');
        Schema::dropIfExists('geo_instrumens');
        Schema::dropIfExists('geo_lerengs');
    }
};
