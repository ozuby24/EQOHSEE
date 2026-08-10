<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemetaan dokumen terkendali ke klausul standar ISO.
 *
 * Dibuat sebagai tabel tersendiri, bukan kolom JSON pada dokumen, karena
 * pertanyaan yang paling sering diajukan justru arah sebaliknya: klausul
 * mana yang belum punya dokumen. Menjawabnya dari JSON menuntut kueri yang
 * berbeda-beda di tiap basis data; dari tabel biasa cukup satu kueri baku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_iso', function (Blueprint $t) {
            $t->id();
            $t->foreignId('document_id')->constrained()->cascadeOnDelete();
            $t->string('standar', 12);     // 9001 | 14001 | 45001 | 50001
            $t->string('klausul', 16);     // mis. 8.1.2
            $t->timestamps();

            // Satu dokumen tidak perlu dipetakan dua kali ke klausul yang sama.
            $t->unique(['document_id', 'standar', 'klausul']);
            $t->index(['standar', 'klausul']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_iso');
    }
};
