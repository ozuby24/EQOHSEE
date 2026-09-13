<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matriks Metode dan Sampel Audit — komponen ke-8 Rencana Audit.
 *
 * Sebelumnya komponen ini satu kotak teks bebas. Berkas audit sungguhan
 * yang menjadi acuan (PT Indo Sejahtera Manunggal 2023) memuatnya sebagai
 * tabel sepanjang delapan puluh baris: satu baris tiap kriteria, dengan
 * metode pembuktian dan daftar sampelnya masing-masing.
 *
 * Bedanya bukan kerapian. Kotak teks bebas tidak dapat menjawab
 * pertanyaan yang paling sering diajukan inspektur atas sebuah temuan —
 * "sub-elemen ini dibuktikan dengan apa, dan sampelnya yang mana" — dan
 * tidak dapat pula menurunkan pengecualian ruang lingkup ke formulir
 * penilaian, sehingga butir yang sudah dinyatakan tidak berlaku tetap
 * ikut membagi nilai akhir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smkp_audits', function (Blueprint $t) {
            $t->json('sampel')->nullable()->after('risiko');
        });
    }

    public function down(): void
    {
        Schema::table('smkp_audits', function (Blueprint $t) {
            $t->dropColumn('sampel');
        });
    }
};
