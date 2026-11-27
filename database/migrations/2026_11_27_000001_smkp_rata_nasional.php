<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rata-rata nasional FR, SR, ASR, MFR dari Surat Edaran Dirjen Minerba
 * (port DBEST #23). Satu baris per tahun SE; menimpa/menambah bawaan
 * SmkpTahap::RATA_NASIONAL. Acuan nasional — bukan data per perusahaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('smkp_rata_nasional')) return;

        Schema::create('smkp_rata_nasional', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('tahun')->unique();
            $t->string('nomor', 120);
            $t->date('tanggal')->nullable();
            $t->decimal('fr', 14, 4);
            $t->decimal('sr', 14, 4);
            $t->decimal('asr', 14, 4);
            $t->decimal('mfr', 14, 4);
            $t->string('catatan', 500)->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smkp_rata_nasional');
    }
};
