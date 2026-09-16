<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smkp_checklist_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pjp_id')->constrained()->cascadeOnDelete();
            $table->foreignId('smkp_checklist_item_id')->constrained()->cascadeOnDelete();
            $table->string('jawaban')->nullable();
            $table->string('nilai')->nullable();
            $table->text('penjelasan')->nullable();
            $table->timestamps();

            $table->unique(['pjp_id', 'smkp_checklist_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smkp_checklist_answers');
    }
};
