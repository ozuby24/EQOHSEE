<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mine_operational_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->date('tanggal');
            $t->string('shift')->default('siang');
            $t->string('pit')->nullable();
            $t->string('area')->nullable();
            $t->string('material')->default('Batubara');
            $t->decimal('produksi_ton', 14, 2)->default(0);
            $t->decimal('overburden_bcm', 14, 2)->default(0);
            $t->decimal('jarak_angkut_km', 10, 2)->default(0);
            $t->unsignedSmallInteger('jumlah_truk')->default(0);
            $t->unsignedSmallInteger('jumlah_excavator')->default(0);
            $t->decimal('jam_operasi', 8, 2)->default(0);
            $t->decimal('jam_delay', 8, 2)->default(0);
            $t->string('status')->default('draft');
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['tanggal', 'shift']);
            $t->index(['pit', 'area']);
            $t->index('status');
        });

        Schema::create('mine_operational_targets', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('tahun');
            $t->unsignedTinyInteger('bulan');
            $t->decimal('target_produksi_ton', 14, 2)->default(0);
            $t->decimal('target_overburden_bcm', 14, 2)->default(0);
            $t->decimal('target_strip_ratio', 10, 3)->nullable();
            $t->decimal('target_jarak_km', 10, 2)->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'tahun', 'bulan']);
        });

        Schema::create('mine_map_layers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('nama');
            $t->string('tipe')->default('area_kerja');
            $t->longText('geojson');
            $t->string('warna')->default('#84cc16');
            $t->string('status')->default('draft');
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['tipe', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mine_map_layers');
        Schema::dropIfExists('mine_operational_targets');
        Schema::dropIfExists('mine_operational_records');
    }
};
