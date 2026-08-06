<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inspeksi dua lapis:
 *   1. TEMPLATE  — jenis inspeksi + daftar parameter yang diperiksa
 *   2. PELAKSANAAN — mengisi template tersebut, bisa oleh beberapa inspektur
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Jenis inspeksi
        Schema::create('inspection_templates', function (Blueprint $t) {
            $t->id();
            $t->string('nama');                                   // mis. "Inspeksi APAR"
            $t->string('jenis')->nullable();                      // Harian | Mingguan | Bulanan | Khusus
            $t->string('kategori')->nullable();                   // Peralatan, Lingkungan, dst.
            $t->text('deskripsi')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        // 2) Parameter yang diperiksa pada tiap jenis
        Schema::create('inspection_template_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('template_id')->constrained('inspection_templates')->cascadeOnDelete();
            $t->string('kelompok')->nullable();                   // pengelompokan parameter
            $t->string('uraian');                                 // parameter yang diperiksa
            $t->string('acuan')->nullable();                      // standar/acuan
            $t->string('risiko_default')->nullable();
            $t->integer('order_index')->default(1);
            $t->timestamps();
        });

        // 3) Inspektur pelaksana (bisa beberapa orang)
        Schema::create('inspection_inspectors', function (Blueprint $t) {
            $t->id();
            $t->foreignId('inspection_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('nama');
            $t->string('jabatan')->nullable();
            $t->string('peran')->default('Anggota');              // Ketua | Anggota
            $t->timestamps();
            $t->index('user_id');
        });

        // 4) Sambungkan pelaksanaan ke template
        Schema::table('inspections', function (Blueprint $t) {
            $t->foreignId('template_id')->nullable()->after('kode')
              ->constrained('inspection_templates')->nullOnDelete();
        });

        Schema::table('inspection_items', function (Blueprint $t) {
            $t->foreignId('template_item_id')->nullable()->after('inspection_id')
              ->constrained('inspection_template_items')->nullOnDelete();
            $t->string('kelompok')->nullable()->after('template_item_id');
            $t->string('acuan')->nullable()->after('uraian');
        });
    }

    public function down(): void
    {
        Schema::table('inspection_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('template_item_id');
            $t->dropColumn(['kelompok','acuan']);
        });
        Schema::table('inspections', fn (Blueprint $t) => $t->dropConstrainedForeignId('template_id'));
        Schema::dropIfExists('inspection_inspectors');
        Schema::dropIfExists('inspection_template_items');
        Schema::dropIfExists('inspection_templates');
    }
};
