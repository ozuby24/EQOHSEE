<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smkp_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('smkp_checklist_category_id')->constrained()->cascadeOnDelete();
            $table->string('grup_kode')->nullable();
            $table->string('grup_nama')->nullable();
            $table->unsignedInteger('nomor');
            $table->text('pertanyaan');
            $table->text('petunjuk')->nullable();
            $table->unsignedInteger('bobot');
            $table->unsignedInteger('urutan');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smkp_checklist_items');
    }
};
