<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit SMKP berjalan dua tahap, bukan sekali jalan.
 *
 * Tahap I menilai kelayakan dan kecukupan dokumentasi auditi, lalu menyusun
 * Rencana Audit. Tahap II adalah audit lapangan. Kolom di bawah menyimpan
 * berkas resmi yang menyertainya, sesuai Kepdirjen 185.K/37.04/DJB/2019.
 *
 * Semuanya JSON karena bentuk isiannya mengikuti formulir yang dapat berubah
 * tanpa mengubah skema — sama seperti kolom `hasil` yang sudah ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smkp_audits', function (Blueprint $t) {
            // 1 = permulaan, 2 = audit lapangan, 3 = pelaporan selesai
            $t->unsignedTinyInteger('tahap')->default(1)->after('status');

            $t->json('permulaan')->nullable()->after('profil');    // kelayakan, kontak awal, mandays
            $t->json('rencana')->nullable()->after('permulaan');   // Rencana Audit 9 butir
            $t->json('kecukupan')->nullable()->after('rencana');   // kecukupan dokumentasi per elemen
            $t->json('kinerja')->nullable()->after('kecukupan');   // data kinerja KP pada periode audit
            $t->json('risiko')->nullable()->after('kinerja');      // top risks yang memandu sampel
        });

        // Daftar hadir rapat pembukaan dan penutupan Tahap II.
        Schema::create('smkp_attendees', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained('smkp_audits')->cascadeOnDelete();
            $t->string('rapat');                       // pembukaan | penutupan
            $t->string('nama');
            $t->string('jabatan')->nullable();
            $t->string('perusahaan')->nullable();
            $t->string('tanda_tangan')->nullable();    // path berkas, bila diunggah
            $t->timestamps();

            $t->index(['audit_id', 'rapat']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smkp_attendees');

        Schema::table('smkp_audits', function (Blueprint $t) {
            $t->dropColumn(['tahap', 'permulaan', 'rencana', 'kecukupan', 'kinerja', 'risiko']);
        });
    }
};
