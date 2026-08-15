<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penanda tangan melekat pada perusahaan.
 *
 * Tabel `signatories` lahir tanpa kolom perusahaan sama sekali, dan
 * akibatnya bukan sekadar daftar yang tercampur: penerbitan sertifikat
 * mengambil `Signatory::where('is_active', true)->first()` — penanda
 * tangan PERTAMA di seluruh sistem. Sertifikat PT A karena itu dapat
 * terbit membawa nama dan GAMBAR TANDA TANGAN pejabat PT B, dan
 * berkasnya terlihat sah sempurna. Tidak ada galat yang menandainya.
 *
 * Kolomnya dibuat nullable dan tidak diisi apa-apa untuk baris yang
 * sudah ada. NULL di sini berarti "milik bersama" mengikuti arti yang
 * sudah dipakai seluruh aplikasi — dan menebak pemilik bagi tanda
 * tangan lama justru menempelkan tanda tangan seseorang pada perusahaan
 * yang belum tentu benar. Yang lama tetap dapat dipakai semua orang
 * sampai ada yang menetapkan pemiliknya secara sadar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('signatories', function (Blueprint $t) {
            $t->foreignId('company_id')->nullable()->after('id')
              ->constrained()->nullOnDelete();
            $t->index(['company_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::table('signatories', function (Blueprint $t) {
            $t->dropIndex(['company_id', 'is_active']);
            $t->dropConstrainedForeignId('company_id');
        });
    }
};
