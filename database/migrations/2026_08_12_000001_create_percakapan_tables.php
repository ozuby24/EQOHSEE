<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Percakapan dan pesan.
 *
 * Dipakai lebih dulu oleh kotak Bantuan (pengguna ↔ asisten AI ↔ admin), tetapi
 * bentuknya sengaja disiapkan untuk chat langsung antar pengguna dan grup
 * perusahaan: yang membedakan hanya kolom `jenis` dan siapa yang terdaftar
 * sebagai peserta. Membuat tabel terpisah untuk tiap jenis percakapan berarti
 * menulis ulang daftar, hitungan belum dibaca, dan lampiran tiga kali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('percakapan', function (Blueprint $t) {
            $t->id();

            // 'bantuan' — pengguna dengan asisten/admin.
            // 'langsung' dan 'grup' menyusul; kolomnya sudah ada supaya
            // penambahannya tidak perlu mengubah tabel yang sudah berisi.
            $t->string('jenis', 20)->default('bantuan');
            $t->string('judul', 200)->nullable();

            // Perusahaan pemilik percakapan — penyekat data antar penyewa.
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('status', 20)->default('terbuka');   // terbuka | selesai
            $t->timestamp('pesan_terakhir_at')->nullable();
            $t->timestamps();

            // Daftar percakapan selalu diurut menurut pesan terakhir.
            $t->index(['jenis', 'status', 'pesan_terakhir_at']);
            $t->index(['company_id', 'pesan_terakhir_at']);
        });

        Schema::create('percakapan_peserta', function (Blueprint $t) {
            $t->id();
            $t->foreignId('percakapan_id')->constrained('percakapan')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Batas baca per peserta, bukan satu penanda untuk seluruh
            // percakapan: pada grup, "sudah dibaca" berbeda tiap orang.
            $t->foreignId('dibaca_sampai_id')->nullable();
            $t->timestamps();

            $t->unique(['percakapan_id', 'user_id']);
        });

        Schema::create('pesan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('percakapan_id')->constrained('percakapan')->cascadeOnDelete();

            // Kosong untuk pesan asisten dan pesan sistem — keduanya tidak
            // berasal dari akun mana pun.
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('peran', 20);   // pengguna | asisten | admin | sistem
            $t->text('isi');
            $t->timestamps();

            $t->index(['percakapan_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pesan');
        Schema::dropIfExists('percakapan_peserta');
        Schema::dropIfExists('percakapan');
    }
};
