<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('konservasi_minerba_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->date('periode');
            $table->string('lokasi');
            $table->string('komoditas');
            $table->string('satuan')->default('ton');
            $table->decimal('target_produksi', 14, 2)->default(0);
            $table->decimal('produksi_aktual', 14, 2)->default(0);
            $table->decimal('material_digali', 14, 2)->default(0);
            $table->decimal('recovery_percent', 6, 2)->default(0);
            $table->decimal('kehilangan_material', 14, 2)->default(0);
            $table->decimal('dilusi', 14, 2)->default(0);
            $table->decimal('stok_akhir', 14, 2)->default(0);
            $table->string('mineral_ikutan')->nullable();
            $table->string('status')->default('draft');
            $table->text('catatan')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['periode', 'komoditas']);
            $table->index(['status', 'company_id']);
        });

        Schema::create('konservasi_minerba_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('record_id')->nullable()->constrained('konservasi_minerba_records')->nullOnDelete();
            $table->string('judul');
            $table->string('kategori')->default('recovery');
            $table->string('prioritas')->default('sedang');
            $table->string('status')->default('rencana');
            $table->string('penanggung_jawab')->nullable();
            $table->date('target_selesai')->nullable();
            $table->text('uraian')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'prioritas']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konservasi_minerba_actions');
        Schema::dropIfExists('konservasi_minerba_records');
    }
};
