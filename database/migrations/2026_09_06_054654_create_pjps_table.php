<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('pjps', function (Blueprint $table) {
            $table->id();
            $table->string('nama_perusahaan');
            $table->string('nib')->nullable();
            $table->string('penanggung_jawab')->nullable();
            $table->text('alamat')->nullable();
            $table->string('tahapan')->default('persyaratan-seleksi-penetapan');
            $table->string('status')->default('aktif');
            $table->text('catatan')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pjps');
    }
};
