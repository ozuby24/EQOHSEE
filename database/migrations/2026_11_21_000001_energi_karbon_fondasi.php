<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Manajemen Energi & Karbon — fondasi data (PRD/FSD Energi-Karbon, fase F0–F1).
 *
 * Seluruhnya MENAMBAH: tabel baru dan kolom nullable/berbawaan pada tabel
 * energi lama. Tidak ada kolom lama yang dibuang, sehingga halaman energi
 * yang sudah dipakai tetap membaca datanya persis seperti sebelumnya.
 *
 *   energi_faktor      registri faktor berversi — NCV, densitas, EF per gas,
 *                      faktor grid, GWP, fraksi FAME, harga, harga karbon.
 *                      company_id NULL = faktor bersama (IPCC/nasional).
 *   energi_sites       lokasi operasi beserta sistem ketenagalistrikannya
 *                      (penentu faktor grid Scope 2).
 *   energi_pengaturan  satu baris per perusahaan: kategori pengguna energi
 *                      (ambang PP 33/2023), set GWP, ambang RAG, tahun dasar.
 *   energi_periode     bulan kalender per perusahaan — satuan kunci data.
 *   energi_koreksi     pengajuan koreksi atas baris yang periodenya terkunci.
 *   energi_jejak       jejak audit per baris: siapa, kapan, nilai lama → baru.
 *
 * Kolom alur (status, verifikator, sumber data) ditambahkan ke lima tabel
 * catatan harian. Baris lama diberi status `terverifikasi` (FSD §14 M4):
 * baris itu sudah dipakai laporan, dan menjadikannya `diajukan` akan
 * membanjiri antrean verifikasi dengan pekerjaan yang sudah selesai.
 */
return new class extends Migration
{
    /** Tabel catatan harian yang menerima kolom alur verifikasi. */
    private const CATATAN = [
        'energy_fuel_logs', 'energy_power_logs', 'energy_other_logs',
        'energy_production', 'energy_fuel_recon',
    ];

    public function up(): void
    {
        if (!Schema::hasColumn('users', 'energi_role')) {
            Schema::table('users', function (Blueprint $t) {
                $t->string('energi_role', 20)->nullable()->after('ohse_role');
            });
        }

        Schema::create('energi_faktor', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kode_energi', 40);
            $t->string('parameter', 30);
            $t->string('kategori_ipcc', 30)->nullable();
            $t->string('wilayah', 30)->nullable();
            $t->string('pemasok', 100)->nullable();
            $t->decimal('nilai', 24, 10);
            $t->string('satuan', 30);
            $t->date('berlaku_mulai');
            $t->date('berlaku_sampai')->nullable();
            $t->unsignedTinyInteger('tier')->default(1);
            $t->string('sumber_dokumen', 255);
            $t->string('sumber_rujukan', 255)->nullable();
            $t->string('sumber_url', 500)->nullable();
            $t->decimal('ketidakpastian_persen', 6, 2)->nullable();
            $t->string('status', 12)->default('draf');
            $t->boolean('perlu_cocok')->default(false);
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('disetujui_pada')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'kode_energi', 'parameter']);
            $t->index(['kode_energi', 'parameter', 'status']);
        });

        Schema::create('energi_sites', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kode', 30);
            $t->string('nama', 150);
            $t->string('jenis', 20)->default('pit');
            $t->string('provinsi', 60)->nullable();
            $t->string('sistem_grid', 30)->nullable();
            $t->string('zona_waktu', 30)->default('Asia/Makassar');
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->unique(['company_id', 'kode']);
        });

        Schema::create('energi_pengaturan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kategori_pengguna', 20)->default('industri');
            $t->decimal('ambang_toe', 12, 2)->nullable();
            $t->string('sistem_grid', 30)->nullable();
            $t->string('gwp_set', 4)->default('AR5');
            $t->boolean('emiten')->default(false);
            $t->decimal('ambang_kelengkapan_persen', 5, 2)->default(95);
            $t->decimal('ambang_rekonsiliasi_persen', 5, 2)->default(2);
            $t->decimal('ambang_rag_persen', 5, 2)->default(5);
            $t->unsignedSmallInteger('tahun_dasar')->nullable();
            $t->decimal('ambang_rekalkulasi_persen', 5, 2)->default(5);
            $t->json('dasar_snapshot')->nullable();
            $t->unsignedSmallInteger('target_tahun')->nullable();
            $t->decimal('target_penurunan_persen', 5, 2)->nullable();
            $t->date('tanggal_proper')->nullable();
            $t->date('tanggal_laporan_tahunan')->nullable();
            $t->timestamps();

            $t->unique('company_id');
        });

        Schema::create('energi_periode', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('tahun');
            $t->unsignedTinyInteger('bulan');
            $t->string('status', 12)->default('terbuka');
            $t->foreignId('dikunci_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('dikunci_pada')->nullable();
            $t->decimal('kelengkapan_persen', 5, 2)->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'tahun', 'bulan']);
        });

        Schema::create('energi_koreksi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('periode_id')->constrained('energi_periode')->cascadeOnDelete();
            $t->string('subjek_type', 100);
            $t->unsignedBigInteger('subjek_id');
            $t->json('nilai_lama')->nullable();
            $t->json('nilai_baru');
            $t->text('alasan');
            $t->string('status', 10)->default('diajukan');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('diputus_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diputus_pada')->nullable();
            $t->string('catatan_putusan', 500)->nullable();
            $t->timestamps();

            $t->index(['company_id', 'status']);
        });

        Schema::create('energi_jejak', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('subjek_type', 100);
            $t->unsignedBigInteger('subjek_id');
            $t->string('aksi', 12);
            $t->json('perubahan')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->nullable();

            $t->index(['company_id', 'subjek_type', 'subjek_id']);
        });

        /* ── kolom tambahan pada tabel lama ── */

        foreach (self::CATATAN as $tabel) {
            Schema::table($tabel, function (Blueprint $t) use ($tabel) {
                $t->string('status', 14)->default('diajukan');
                $t->string('sumber_data', 12)->nullable();
                $t->foreignId('diinput_oleh')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('diverifikasi_pada')->nullable();
                $t->string('alasan_tolak', 500)->nullable();

                if (in_array($tabel, ['energy_power_logs', 'energy_other_logs'], true)) {
                    $t->foreignId('site_id')->nullable()->constrained('energi_sites')->nullOnDelete();
                }
            });

            DB::table($tabel)->update(['status' => 'terverifikasi']);
        }

        /* `jenis` energi lain kini KODE katalog (lpg, bensin, gas, …), bukan
           label; mesin GRK memilih faktor menurut kode itu. Label lama yang
           dikenal diseragamkan; selebihnya ditandai "kode tidak dikenal". */
        foreach (['LPG' => 'lpg', 'Gas' => 'gas', 'Bensin' => 'bensin', 'Solar' => 'solar'] as $lama => $kode) {
            DB::table('energy_other_logs')->where('jenis', $lama)->update(['jenis' => $kode]);
        }

        Schema::table('energy_fuel_logs', function (Blueprint $t) {
            $t->decimal('persen_fame', 5, 2)->nullable();
            $t->index(['status', 'tanggal']);
        });

        Schema::table('energy_production', function (Blueprint $t) {
            $t->decimal('stripping_ratio', 8, 3)->nullable();
            $t->decimal('jarak_angkut_km', 8, 2)->nullable();
        });

        Schema::table('energy_equipment', function (Blueprint $t) {
            $t->foreignId('site_id')->nullable()->constrained('energi_sites')->nullOnDelete();
            $t->string('kategori_ipcc', 20)->default('bergerak_offroad');
            $t->boolean('seu')->default(false);
        });

        Schema::table('energy_baselines', function (Blueprint $t) {
            $t->string('status', 16)->default('usulan');
            $t->decimal('target_tco2e_ton', 12, 6)->nullable();
            $t->json('model_enb')->nullable();
            $t->foreignId('diusulkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('disetujui_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('disetujui_pada')->nullable();
        });

        /* Baseline yang sudah ada adalah baseline yang sudah dipakai —
           memaksanya kembali menjadi usulan akan membuat dasbor kehilangan
           pembandingnya sampai direksi menyetujuinya ulang. */
        DB::table('energy_baselines')->update(['status' => 'disetujui_bod']);

        Schema::table('energy_opportunities', function (Blueprint $t) {
            $t->decimal('capex_rp', 16, 2)->nullable();
            $t->string('metode_mv', 2)->nullable();
            $t->decimal('hemat_terverifikasi_liter', 12, 2)->nullable();
            $t->decimal('hemat_terverifikasi_kwh', 12, 2)->nullable();
            $t->date('tanggal_mulai_hemat')->nullable();
            $t->text('mv_catatan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('energy_opportunities', function (Blueprint $t) {
            $t->dropColumn(['capex_rp', 'metode_mv', 'hemat_terverifikasi_liter',
                            'hemat_terverifikasi_kwh', 'tanggal_mulai_hemat', 'mv_catatan']);
        });

        Schema::table('energy_baselines', function (Blueprint $t) {
            $t->dropConstrainedForeignId('diusulkan_oleh');
            $t->dropConstrainedForeignId('disetujui_oleh');
            $t->dropColumn(['status', 'target_tco2e_ton', 'model_enb', 'disetujui_pada']);
        });

        Schema::table('energy_equipment', function (Blueprint $t) {
            $t->dropConstrainedForeignId('site_id');
            $t->dropColumn(['kategori_ipcc', 'seu']);
        });

        Schema::table('energy_production', function (Blueprint $t) {
            $t->dropColumn(['stripping_ratio', 'jarak_angkut_km']);
        });

        Schema::table('energy_fuel_logs', function (Blueprint $t) {
            $t->dropIndex(['status', 'tanggal']);
            $t->dropColumn('persen_fame');
        });

        foreach (self::CATATAN as $tabel) {
            Schema::table($tabel, function (Blueprint $t) use ($tabel) {
                if (in_array($tabel, ['energy_power_logs', 'energy_other_logs'], true)) {
                    $t->dropConstrainedForeignId('site_id');
                }
                $t->dropConstrainedForeignId('diinput_oleh');
                $t->dropConstrainedForeignId('diverifikasi_oleh');
                $t->dropColumn(['status', 'sumber_data', 'diverifikasi_pada', 'alasan_tolak']);
            });
        }

        foreach (['energi_jejak', 'energi_koreksi', 'energi_periode',
                  'energi_pengaturan', 'energi_sites', 'energi_faktor'] as $tabel) {
            Schema::dropIfExists($tabel);
        }

        if (Schema::hasColumn('users', 'energi_role')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropColumn('energi_role');
            });
        }
    }
};
