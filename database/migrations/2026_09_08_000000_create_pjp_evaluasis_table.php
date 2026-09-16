<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pjp_evaluasis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pjp_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('tahun');
            $table->unsignedTinyInteger('semester');
            $table->unsignedTinyInteger('skor_teknis');
            $table->unsignedTinyInteger('skor_keselamatan_kesehatan');
            $table->unsignedTinyInteger('skor_lingkungan');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['pjp_id', 'tahun', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pjp_evaluasis');
    }
};
