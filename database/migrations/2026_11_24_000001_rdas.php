<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rehabilitasi DAS — kewajiban penanaman pemegang PPKH sampai serah
 * terima tanaman (PP 26/2020, Permen LHK P.59/2019, Permen LHK 7/2021).
 *
 * Diadaptasi dari modul RehabDas eQOHSE dan bahan Bimtek BPDAS Mahakam
 * Berau. Hierarki datanya mengikuti dokumen di lapangan:
 *  - rdas_ppkh      — satu SK PPKH dan luas kewajibannya (1:1 luas PAK),
 *                     batas waktu ½N, status reklamasi hutannya;
 *  - rdas_blok      — satu lokasi yang ditetapkan (SK Penlok) dengan RKP,
 *                     komposisi jenis, dan tanggal tanam P0;
 *  - rdas_petak     — petak tanam dengan poligon peta dan hasil petak ukur
 *                     terakhir;
 *  - rdas_pantau    — riwayat Petak Ukur (IS 5%): persen tumbuh, batang
 *                     sehat/ha, kebutuhan sulam;
 *  - rdas_kegiatan  — log pelaksanaan kontraktor yang diverifikasi;
 *  - rdas_penilaian — permohonan penilaian, tim penilai, hasil, BAST
 *                     (boleh bertahap);
 *  - rdas_laporan   — laporan semesteran;
 *  - rdas_kejadian  — gangguan (kebakaran, banjir, perambahan) dan tangga
 *                     sanksi (teguran sampai penindakan hukum).
 *
 * Setiap tabel membawa company_id-nya sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rdas_ppkh', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kode', 40);
            $t->string('nomor_sk', 150);
            $t->date('tanggal_sk');
            $t->unsignedSmallInteger('jangka_tahun');
            $t->date('berlaku_sampai');
            $t->string('tujuan', 20)->default('komersial');      // komersial, non_komersial
            $t->string('kegiatan', 150);
            $t->string('provinsi', 60);
            $t->string('kabupaten', 80)->nullable();
            $t->string('bpdas', 100);
            $t->decimal('luas_ppkh_ha', 12, 2);
            $t->string('nomor_pak', 150)->nullable();             // Penetapan Areal Kerja
            $t->decimal('luas_pak_ha', 12, 2);
            $t->decimal('rasio', 4, 2)->default(1);
            $t->decimal('kewajiban_ha', 12, 2);
            $t->date('batas_selesai');                            // ½N atau ½N + 1
            $t->string('status_lokasi', 20)->default('belum_diajukan'); // belum_diajukan, diajukan, ditetapkan
            // Reklamasi hutan — kewajiban saudara yang dinilai direktorat yang sama.
            $t->string('rurh_status', 20)->default('belum');      // belum, telaah, sidang_1, sidang_2, disetujui
            $t->decimal('luas_terganggu_ha', 12, 2)->default(0);
            $t->decimal('luas_direklamasi_ha', 12, 2)->default(0);
            $t->decimal('nilai_reklamasi', 5, 1)->nullable();     // 0–100: ≥ 80 baik
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'kode']);
            $t->index(['company_id', 'status_lokasi']);
        });

        Schema::create('rdas_blok', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('ppkh_id')->constrained('rdas_ppkh')->cascadeOnDelete();
            $t->string('kode', 40);
            $t->string('nama', 150);
            $t->string('sk_penlok_nomor', 150)->nullable();
            $t->date('sk_penlok_tanggal')->nullable();
            $t->string('das', 80);
            $t->string('sub_das', 80)->nullable();
            $t->string('kabupaten', 80)->nullable();
            $t->string('kecamatan', 80)->nullable();
            $t->string('desa', 120)->nullable();
            $t->string('fungsi_kawasan', 10)->default('HL');     // HL, HP, HPT, HPK, KSA, KPA, APL
            $t->string('pemangku', 150)->nullable();
            $t->string('ekosistem', 20)->default('daratan');     // daratan, mangrove, gambut, riparian
            $t->decimal('luas_ha', 10, 2);
            $t->string('jarak_tanam', 20)->default('4 × 4 m');
            $t->unsignedSmallInteger('kerapatan')->default(625); // batang/ha
            $t->json('komposisi')->nullable();
            $t->string('rkp_nomor', 150)->nullable();            // Rencana Kegiatan Penanaman
            $t->date('rkp_disahkan_at')->nullable();
            $t->date('tanggal_tanam')->nullable();               // P0
            $t->string('kontraktor', 150)->nullable();
            $t->decimal('anggaran_rp', 18, 2)->default(0);
            $t->string('fase', 20)->default('penetapan');
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->unsignedInteger('geotag_total')->default(0);     // tanaman tergeotag (self declare)
            $t->unsignedInteger('geotag_duplikat')->default(0);
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'kode']);
            $t->index(['company_id', 'fase']);
        });

        Schema::create('rdas_petak', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('blok_id')->constrained('rdas_blok')->cascadeOnDelete();
            $t->string('kode', 40);
            $t->decimal('luas_ha', 8, 2);
            $t->string('kelerengan', 20)->nullable();            // datar, landai, agak_curam, curam
            $t->json('poligon')->nullable();                     // [[lat, lng], ...]
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->unsignedInteger('bibit_tanam')->default(0);
            $t->unsignedInteger('bibit_sulam')->default(0);
            $t->decimal('persen_tumbuh', 5, 2)->nullable();      // salinan PU terakhir
            $t->decimal('sehat_ha', 8, 1)->nullable();
            $t->string('status_tumbuh', 20)->nullable();         // berhasil, peringatan, sulam
            $t->date('dipantau_at')->nullable();
            $t->timestamps();
            $t->unique(['blok_id', 'kode']);
            $t->index(['company_id', 'blok_id']);
        });

        Schema::create('rdas_pantau', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('petak_id')->constrained('rdas_petak')->cascadeOnDelete();
            $t->foreignId('blok_id')->constrained('rdas_blok')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kode', 40);
            $t->date('tanggal');
            $t->string('periode', 20);                           // p0, p1, p2, penilaian, khusus
            $t->unsignedSmallInteger('jumlah_pu');
            $t->decimal('luas_pu_ha', 6, 3)->default(0.1);
            $t->decimal('intensitas_pct', 5, 2)->default(0);
            $t->unsignedInteger('diamati');
            $t->unsignedInteger('sehat');
            $t->unsignedInteger('merana');
            $t->unsignedInteger('mati');
            $t->decimal('persen_tumbuh', 5, 2);
            $t->decimal('sehat_ha', 8, 1);
            $t->decimal('hidup_ha', 8, 1);
            $t->decimal('tinggi_cm', 7, 1)->nullable();
            $t->decimal('diameter_mm', 6, 1)->nullable();
            $t->string('status', 20);                            // berhasil, peringatan, sulam
            $t->unsignedInteger('kebutuhan_sulam')->default(0);
            $t->string('pengamat', 120);
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'kode']);
            $t->index(['company_id', 'tanggal']);
            $t->index(['petak_id', 'tanggal']);
        });

        Schema::create('rdas_kegiatan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('blok_id')->constrained('rdas_blok')->cascadeOnDelete();
            $t->foreignId('petak_id')->nullable()->constrained('rdas_petak')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->date('tanggal');
            $t->string('jenis', 30);
            $t->string('fase', 10);                              // P0, P1, P2
            $t->decimal('luas_ha', 8, 2)->default(0);
            $t->unsignedInteger('bibit')->default(0);
            $t->unsignedInteger('hok')->default(0);              // hari orang kerja
            $t->decimal('biaya_rp', 16, 2)->default(0);
            $t->string('pelaksana', 150)->nullable();
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->string('status', 20)->default('diajukan');       // diajukan, diverifikasi, ditolak
            $t->string('verifikator', 120)->nullable();
            $t->timestamp('diverifikasi_at')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'tanggal']);
            $t->index(['blok_id', 'tanggal']);
        });

        Schema::create('rdas_penilaian', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('blok_id')->constrained('rdas_blok')->cascadeOnDelete();
            $t->string('kode', 40);
            $t->unsignedTinyInteger('tahap')->default(1);        // serah terima bertahap
            $t->decimal('luas_diajukan_ha', 10, 2);
            $t->decimal('luas_berhasil_ha', 10, 2)->nullable();
            $t->string('status', 20)->default('diajukan');       // diajukan, tim_dibentuk, dinilai, berhasil, tidak_berhasil, ditunda, serah_terima
            $t->date('diajukan_at');
            $t->string('sk_tim_nomor', 150)->nullable();
            $t->date('sk_tim_at')->nullable();
            $t->date('jadwal_mulai')->nullable();
            $t->date('jadwal_selesai')->nullable();
            $t->string('ketua_tim', 150)->nullable();
            $t->decimal('persen_tumbuh', 5, 2)->nullable();
            $t->decimal('sehat_ha', 8, 1)->nullable();
            $t->json('prasyarat')->nullable();                   // salinan cek kelayakan saat diajukan
            $t->string('bast_nomor', 150)->nullable();
            $t->date('bast_at')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'kode']);
            $t->index(['company_id', 'status']);
        });

        Schema::create('rdas_laporan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('ppkh_id')->constrained('rdas_ppkh')->cascadeOnDelete();
            $t->unsignedSmallInteger('tahun');
            $t->unsignedTinyInteger('semester');
            $t->date('batas');
            $t->date('dikirim_at')->nullable();
            $t->string('tanda_terima', 150)->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->unique(['ppkh_id', 'tahun', 'semester']);
            $t->index(['company_id', 'batas']);
        });

        Schema::create('rdas_kejadian', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('ppkh_id')->constrained('rdas_ppkh')->cascadeOnDelete();
            $t->foreignId('blok_id')->nullable()->constrained('rdas_blok')->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('jenis', 40);
            $t->date('tanggal');
            $t->decimal('luas_terdampak_ha', 10, 2)->default(0);
            $t->text('uraian');
            $t->string('strategi', 20)->nullable();              // replanting, relokasi, parsial
            $t->string('ba_nomor', 150)->nullable();
            $t->date('dilaporkan_at')->nullable();               // ke BPDAS
            $t->string('status', 20)->default('terbuka');        // terbuka, ditangani, selesai
            $t->timestamps();
            $t->index(['company_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        foreach (['rdas_kejadian', 'rdas_laporan', 'rdas_penilaian', 'rdas_kegiatan', 'rdas_pantau', 'rdas_petak', 'rdas_blok', 'rdas_ppkh'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
