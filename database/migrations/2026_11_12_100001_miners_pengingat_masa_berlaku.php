<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan pengingat masa berlaku Miners (Alert:MasaBerlaku Safe Track).
 *
 * Satu baris per dokumen + tanggal berakhir + ambang yang sudah
 * dikirimkan. Kuncinya unik, sehingga perintah yang dijalankan dua kali
 * pada hari yang sama — atau penjadwal yang menyusul — tidak pernah
 * mengirim pengingat yang sama dua kali. Bila dokumennya diperpanjang,
 * tanggal berakhirnya berubah dan jadwal pengingatnya mulai lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mnr_pengingat')) return;

        Schema::create('mnr_pengingat', function (Blueprint $t) {
            $t->id();
            $t->string('jenis', 16);
            $t->unsignedBigInteger('dokumen_id');
            $t->date('berakhir');
            $t->unsignedSmallInteger('ambang');
            $t->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $t->text('penerima')->nullable();
            $t->timestamp('dikirim_pada')->nullable();
            $t->timestamps();
            $t->unique(['jenis', 'dokumen_id', 'berakhir', 'ambang'], 'mnr_pengingat_unik');
            $t->index(['company_id', 'dikirim_pada'], 'mnr_pengingat_perusahaan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mnr_pengingat');
    }
};
