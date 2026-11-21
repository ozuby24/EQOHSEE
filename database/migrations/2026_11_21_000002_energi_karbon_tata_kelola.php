<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manajemen Energi & Karbon — tata kelola (PRD/FSD Energi-Karbon, fase F2–F3).
 *
 *   energi_grk_batas     batas organisasi & operasional per tahun (ISO 14064-1,
 *                        GHG Protocol), pengecualian, penyaringan relevansi
 *                        15 kategori Scope 3.
 *   energi_scope3        data aktivitas Scope 3 per kategori GHG Protocol.
 *   energi_mitigasi      registri aksi mitigasi, siap didaftarkan ke SRN PPI.
 *   energi_personel      Manajer Energi & Auditor Energi bersertifikat
 *                        (Permen ESDM 8/2025 Ps. 4).
 *   energi_program       program konservasi energi tahunan.
 *   energi_audit         audit energi (≥1×/3 tahun, mencakup ≥1 SEU).
 *   energi_rekomendasi   rekomendasi audit, tenggat ≤3 tahun (Ps. 16).
 *   energi_tinjauan      monev tahunan ke manajemen puncak & tinjauan
 *                        manajemen ISO 50001.
 *   energi_laporan       laporan terbit berversi beserta snapshot bekunya.
 *
 * Instruksi BOD tidak mendapat tabel sendiri: ia TindakLanjut bermodul
 * `energi`, sehingga masuk ke pengingat, daftar, dan penutupan yang sudah
 * dipakai seluruh modul lain.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('energi_grk_batas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('tahun');
            $t->string('pendekatan', 24)->default('kendali_operasional');
            $t->decimal('persen_ekuitas', 5, 2)->nullable();
            $t->text('uraian_batas')->nullable();
            $t->json('pengecualian')->nullable();
            $t->json('relevansi_scope3')->nullable();
            $t->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('disetujui_pada')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'tahun']);
        });

        Schema::create('energi_scope3', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('tahun');
            $t->unsignedTinyInteger('bulan')->nullable();
            $t->unsignedTinyInteger('kategori');
            $t->string('subkategori', 150);
            $t->string('metode', 20)->default('average_data');
            $t->decimal('kuantitas', 18, 4);
            $t->string('satuan', 20);
            $t->decimal('faktor_kgco2e', 18, 8);
            $t->string('sumber_faktor', 255);
            $t->string('mitra', 150)->nullable();
            $t->unsignedTinyInteger('kualitas_data')->default(3);
            $t->string('status', 14)->default('diajukan');
            $t->foreignId('diinput_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->string('catatan', 500)->nullable();
            $t->timestamps();

            $t->index(['company_id', 'tahun', 'kategori']);
        });

        Schema::create('energi_mitigasi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('site_id')->nullable()->constrained('energi_sites')->nullOnDelete();
            $t->foreignId('peluang_id')->nullable()->constrained('energy_opportunities')->nullOnDelete();
            $t->string('judul', 200);
            $t->string('jenis', 20);
            $t->date('mulai');
            $t->date('selesai')->nullable();
            $t->string('metodologi', 255)->nullable();
            $t->decimal('baseline_tco2e_tahun', 14, 2)->nullable();
            $t->decimal('target_penurunan_tco2e_tahun', 14, 2)->nullable();
            $t->json('capaian')->nullable();
            $t->string('status_srn', 14)->default('belum');
            $t->string('nomor_srn', 60)->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'status_srn']);
        });

        Schema::create('energi_personel', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('nama', 150);
            $t->string('peran', 16);
            $t->boolean('internal')->default(true);
            $t->string('lembaga', 150)->nullable();
            $t->string('nomor_sertifikat', 100)->nullable();
            $t->string('lembaga_sertifikasi', 150)->nullable();
            $t->date('berlaku_sampai')->nullable();
            $t->string('nomor_sk', 100)->nullable();
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'peran', 'aktif']);
        });

        Schema::create('energi_program', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('tahun');
            $t->string('judul', 200);
            $t->text('sasaran')->nullable();
            $t->decimal('target_hemat_gj', 14, 2)->nullable();
            $t->decimal('target_hemat_tco2e', 14, 2)->nullable();
            $t->decimal('anggaran_rp', 16, 2)->nullable();
            $t->string('pic', 150)->nullable();
            $t->string('status', 12)->default('rencana');
            $t->json('peluang_ids')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'tahun']);
        });

        Schema::create('energi_audit', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('jenis', 10)->default('internal');
            $t->string('judul', 200);
            $t->text('ruang_lingkup')->nullable();
            $t->json('seu')->nullable();
            $t->json('auditor_ids')->nullable();
            $t->date('mulai');
            $t->date('selesai')->nullable();
            $t->string('standar', 100)->nullable();
            $t->string('status', 14)->default('direncanakan');
            $t->text('ringkasan')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'selesai']);
        });

        Schema::create('energi_rekomendasi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('audit_id')->constrained('energi_audit')->cascadeOnDelete();
            $t->foreignId('peluang_id')->nullable()->constrained('energy_opportunities')->nullOnDelete();
            $t->text('uraian');
            $t->decimal('potensi_hemat_gj', 14, 2)->nullable();
            $t->decimal('potensi_hemat_rp', 16, 2)->nullable();
            $t->decimal('biaya_rp', 16, 2)->nullable();
            $t->string('prioritas', 10)->default('sedang');
            $t->date('tenggat');
            $t->string('status', 16)->default('terbuka');
            $t->string('alasan_tidak_layak', 500)->nullable();
            $t->timestamps();

            $t->index(['company_id', 'status', 'tenggat']);
        });

        Schema::create('energi_tinjauan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('tahun');
            $t->string('jenis', 20);
            $t->date('tanggal');
            $t->string('peserta', 500)->nullable();
            $t->text('masukan')->nullable();
            $t->text('keluaran')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'tahun', 'jenis']);
        });

        Schema::create('energi_laporan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('templat', 20);
            $t->unsignedSmallInteger('tahun');
            $t->date('periode_mulai');
            $t->date('periode_sampai');
            $t->unsignedSmallInteger('versi')->default(1);
            $t->string('status', 14)->default('draf');
            $t->string('judul', 200);
            $t->string('nomor_dokumen', 80)->nullable();
            $t->json('snapshot')->nullable();
            $t->string('hash_snapshot', 64)->nullable();
            $t->foreignId('disusun_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('disetujui_pada')->nullable();
            $t->foreignId('diterbitkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diterbitkan_pada')->nullable();
            $t->date('disampaikan_pada')->nullable();
            $t->string('bukti_penyampaian', 255)->nullable();
            $t->foreignId('pengganti_id')->nullable()->constrained('energi_laporan')->nullOnDelete();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'templat', 'tahun']);
        });
    }

    public function down(): void
    {
        foreach (['energi_laporan', 'energi_tinjauan', 'energi_rekomendasi', 'energi_audit',
                  'energi_program', 'energi_personel', 'energi_mitigasi', 'energi_scope3',
                  'energi_grk_batas'] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }
};
