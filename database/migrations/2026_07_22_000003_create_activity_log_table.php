<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Log aktivitas lintas modul (lms / smkp / tpkkp)
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('username')->nullable();
            $t->string('module')->default('lms');
            $t->string('action');
            $t->text('detail')->nullable();
            $t->timestamps();
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
