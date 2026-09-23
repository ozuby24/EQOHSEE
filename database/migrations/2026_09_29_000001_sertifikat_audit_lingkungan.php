<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sertifikat Penghargaan Kinerja Lingkungan.
 *
 * Terbit dari audit yang seluruh kriterianya sudah diverifikasi dan yang
 * predikatnya (ADITAMA, UTAMA, PRATAMA) memang terbit.
 *
 * ── Tabel sendiri, bukan kolom pada env_audits ──
 *
 * Sertifikat yang dicabut harus tetap dapat diperiksa. QR-nya sudah
 * tercetak dan mungkin sudah dipajang; yang memindainya sesudah
 * pencabutan harus membaca "DICABUT", bukan "tidak ditemukan" — yang
 * terakhir itu tidak dapat dibedakan dari sertifikat palsu. Kolom pada
 * auditnya hanya dapat menyimpan satu terbitan, dan terbitan ulang akan
 * menimpa yang lama.
 *
 * Karena itu pula audit_id nullOnDelete, bukan cascade: menghapus
 * auditnya tidak boleh menghapus jejak bahwa sertifikat itu pernah
 * terbit.
 *
 * ── `data` adalah potret ──
 *
 * Nama perusahaan, skor per bagian, dan penanda tangan dibekukan saat
 * terbit. Sertifikat yang sudah ditandatangani tidak boleh berubah
 * bunyinya diam-diam karena nilai auditnya disunting sesudahnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('env_audit_sertifikat', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->nullable()->constrained('env_audits')->nullOnDelete();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('signatory_id')->nullable()->constrained()->nullOnDelete();

            $t->string('nomor', 64)->unique();
            // Kode acak untuk QR — nomor urut dapat ditebak, kode ini tidak.
            $t->string('kode', 32)->unique();

            $t->date('terbit');
            $t->date('berlaku')->nullable();
            $t->string('tempat', 120)->nullable();

            $t->string('predikat', 20);
            $t->string('peringkat', 20);
            $t->decimal('skor', 5, 2);

            $t->json('data');

            $t->timestamp('dicabut_at')->nullable();
            $t->string('alasan_cabut', 500)->nullable();
            $t->timestamps();

            $t->index(['audit_id', 'dicabut_at']);
            // Batas perusahaan (MilikPerusahaan) menyaring setiap kueri
            // lewat company_id; tanpa indeks ini tabelnya dipindai penuh.
            $t->index(['company_id', 'terbit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('env_audit_sertifikat');
    }
};
