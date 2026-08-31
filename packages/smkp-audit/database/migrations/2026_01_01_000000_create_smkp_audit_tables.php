<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit SMKP Minerba — 7 elemen sesuai Kepdirjen 185.K/37.04/DJB/2019.
 *
 * Satu baris per periode audit per perusahaan. Penilaian tiap kriteria dan
 * seluruh isian formulir disimpan sebagai JSON supaya struktur elemen dan
 * bentuk formulirnya dapat berubah lewat berkas acuan, tanpa migrasi ulang.
 *
 * Kunci asing ke tabel perusahaan dan pengguna dipasang HANYA bila tabelnya
 * memang ada. Modul ini dipasang pada aplikasi yang belum tentu punya
 * keduanya, dan migrasi yang gagal di tengah meninggalkan basis data setengah
 * jadi — jauh lebih merepotkan daripada kolom tanpa kunci asing.
 */
return new class extends Migration
{
    public function up(): void
    {
        $perusahaan = (string) config('smkp.perusahaan.tabel', 'companies');
        $pengguna   = config('smkp.tabel_pengguna', 'users');

        Schema::create('smkp_audits', function (Blueprint $t) use ($perusahaan, $pengguna) {
            $t->id();

            if (Schema::hasTable($perusahaan)) {
                $t->foreignId('company_id')->nullable()->constrained($perusahaan)->nullOnDelete();
            } else {
                $t->unsignedBigInteger('company_id')->nullable();
            }

            $t->unsignedSmallInteger('tahun');
            $t->string('judul')->nullable();
            $t->string('status')->default('draft');          // draft | berjalan | selesai
            $t->unsignedTinyInteger('tahap')->default(1);    // 1 permulaan | 2 lapangan | 3 pelaporan
            $t->date('tanggal_mulai')->nullable();
            $t->date('tanggal_selesai')->nullable();
            $t->string('ketua_auditor')->nullable();

            $t->json('hasil')->nullable();       // hasil[kodeButir] = {v, ket, bukti}
            $t->json('auditor')->nullable();     // [{nama, peran, kompetensi}]
            $t->json('profil')->nullable();      // ruang lingkup, lokasi, KTT, catatan
            $t->json('permulaan')->nullable();   // kelayakan, kontak awal, mandays
            $t->json('rencana')->nullable();     // Rencana Audit 9 komponen
            $t->json('kecukupan')->nullable();   // kecukupan dokumentasi per elemen
            $t->json('kinerja')->nullable();     // data kinerja KP pada periode audit
            $t->json('risiko')->nullable();      // top risks yang memandu sampel

            if ($pengguna && Schema::hasTable($pengguna)) {
                $t->foreignId('user_id')->nullable()->constrained($pengguna)->nullOnDelete();
            } else {
                $t->unsignedBigInteger('user_id')->nullable();
            }

            $t->timestamps();

            $t->unique(['company_id', 'tahun']);
        });

        Schema::create('smkp_findings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained('smkp_audits')->cascadeOnDelete();
            $t->string('kode_kriteria');                  // mis. IV.2.1
            $t->string('jenis');                          // mayor | minor | observasi
            $t->text('uraian');
            $t->text('akar_masalah')->nullable();
            $t->text('tindakan')->nullable();             // tindakan perbaikan
            $t->string('penanggung_jawab')->nullable();
            $t->date('target_selesai')->nullable();
            $t->date('tanggal_selesai')->nullable();
            $t->string('status')->default('Open');        // Open | In Progress | Closed
            $t->text('verifikasi')->nullable();
            $t->timestamps();

            $t->index(['audit_id', 'status']);
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
        Schema::dropIfExists('smkp_findings');
        Schema::dropIfExists('smkp_audits');
    }
};
