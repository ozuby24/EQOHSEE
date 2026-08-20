<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengujian (metode PJ) — kuis kesadaran risiko untuk pekerja tambang.
 *
 * Tabel terpisah dari `tpkkp_responses`, bukan menumpang padanya.
 * Bentuk datanya memang lain: kuesioner menyimpan JAWABAN per butir
 * (yang dirata-rata jadi skor), pengujian menyimpan HASIL satu sesi
 * (benar/total, tingkat, durasi, berapa kali layar ditinggalkan).
 * Menumpangkannya berarti separuh kolom selalu kosong pada tiap baris,
 * dan tiap kueri harus menyebut `where jenis = …` yang cepat atau
 * lambat akan terlupa di satu tempat.
 *
 * `kunci_identitas` unik PER PERUSAHAAN: satu orang mengerjakan sekali
 * untuk tautan yang sama. Unik secara global akan salah — nama yang
 * sama dapat bekerja di dua perusahaan berbeda, dan pengujian keduanya
 * adalah pengujian yang berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tpkkp_pengujian', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->cascadeOnDelete();
            $t->string('ext_id')->nullable();

            $t->string('nama');
            $t->string('nrp')->nullable();
            $t->string('jabatan')->nullable();
            $t->string('dept')->nullable();
            $t->string('perusahaan')->nullable();
            $t->string('kunci_identitas');

            $t->unsignedSmallInteger('benar')->default(0);
            $t->unsignedSmallInteger('total')->default(0);
            $t->decimal('skor_pct', 5, 4)->default(0);   // 0.0000 – 1.0000
            $t->unsignedTinyInteger('tingkat')->default(1);

            $t->unsignedInteger('durasi_detik')->default(0);
            $t->unsignedSmallInteger('pindah_layar')->default(0);

            $t->timestamp('mulai')->nullable();
            $t->timestamp('ts')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'kunci_identitas']);
            $t->index(['company_id', 'ts']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tpkkp_pengujian');
    }
};
