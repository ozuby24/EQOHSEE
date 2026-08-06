<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website #2 — Safety Maturity Level (folder "SML").
 * Konversi dari skema Supabase project qxaovlmbqbxfniftncie.
 *
 * Yang SUDAH tertutup inti bersama (tidak dibuat ulang):
 *   companies      -> tabel companies (inti)
 *   app_users      -> tabel users + audit_role (inti)
 *   sessions       -> ditangani Laravel
 *   activity_logs  -> tabel activity_log (module = 'tpkkp')
 *   app_settings   -> tabel app_settings (key/value: 'tpkkp_prog_target')
 *
 * Sisa yang perlu dibuat: 2 tabel di bawah.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Penilaian TPKKP — satu baris per perusahaan (dulu PK = company_id)
        Schema::create('tpkkp_assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $t->json('scores')->nullable();    // nilai per parameter
            $t->json('profil')->nullable();    // nama, jenis, site, komoditas, ktt, tahun, POP
            $t->json('strata')->nullable();    // baris strata tenaga kerja
            $t->json('programs')->nullable();  // program keselamatan
            $t->timestamps();
        });

        // Respons kuesioner
        Schema::create('tpkkp_responses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('ext_id')->nullable();      // id lama dari klien (dedup saat migrasi)
            $t->string('cat')->nullable();         // kategori responden
            $t->string('nrp')->nullable();
            $t->string('jabatan')->nullable();
            $t->string('dept')->nullable();
            $t->string('perusahaan')->nullable();
            $t->json('answers')->nullable();
            $t->timestamp('ts')->nullable();
            $t->timestamps();
            $t->index('company_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tpkkp_responses');
        Schema::dropIfExists('tpkkp_assessments');
    }
};
