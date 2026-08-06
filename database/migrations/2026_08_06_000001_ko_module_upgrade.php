<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * KO / SPIP — pelengkap skema agar Indeks KO bisa dihitung utuh
 * dan setiap peringatan punya tindak lanjut.
 *
 * SPIP = Sarana · Prasarana · Instalasi · Peralatan (empat kategori).
 *
 * Yang ditambahkan:
 *   ko_safeguards   perangkat pengaman per objek  → sub-elemen 2
 *   ko_inspections  riwayat PM / pengaman / sertifikasi
 *   ko_actions      tindak lanjut yang lahir dari peringatan
 *   kolom tautan pada ko_objects, ko_reviews, ko_personnel
 *   users.ko_role
 *   nilai bawaan app_settings ko_*
 *
 * APAR TIDAK disimpan di sini — perangkat pengaman jenis APAR menunjuk
 * ke aset SIGAP lewat kolom sigap_asset_id (tanpa foreign key, karena
 * modul SIGAP bisa saja belum terpasang).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Perangkat pengaman per objek (sub-elemen 2) ──
        if (!Schema::hasTable('ko_safeguards')) {
            Schema::create('ko_safeguards', function (Blueprint $t) {
                $t->id();
                $t->foreignId('ko_object_id')->constrained('ko_objects')->cascadeOnDelete();
                $t->string('nama');                         // Grounding, Emergency Stop, Pull-cord…
                $t->string('spesifikasi')->nullable();      // nilai ukur: "2,1 Ohm", "14 titik"
                $t->string('status')->default('Berfungsi'); // Berfungsi | Perlu Perbaikan | Tidak Berfungsi
                $t->date('tgl_periksa')->nullable();
                $t->text('catatan')->nullable();

                // APAR & proteksi kebakaran dikelola di SIGAP — di sini hanya rujukan
                $t->unsignedBigInteger('sigap_asset_id')->nullable()->index();

                $t->timestamps();
                $t->index(['ko_object_id', 'status']);
            });
        }

        // ── Riwayat pemeriksaan: PM, pengaman, sertifikasi ──
        if (!Schema::hasTable('ko_inspections')) {
            Schema::create('ko_inspections', function (Blueprint $t) {
                $t->id();
                $t->foreignId('ko_object_id')->constrained('ko_objects')->cascadeOnDelete();
                $t->foreignId('ko_safeguard_id')->nullable()->constrained('ko_safeguards')->nullOnDelete();
                $t->foreignId('ko_personnel_id')->nullable()->constrained('ko_personnel')->nullOnDelete();

                $t->string('jenis');                        // PM | Pengaman | Sertifikasi
                $t->date('tanggal');
                $t->string('hasil')->nullable();            // Baik | Perlu Perbaikan | Tidak Lulus
                $t->string('nilai_ukur')->nullable();
                $t->date('berikutnya')->nullable();
                $t->text('catatan')->nullable();
                $t->string('lampiran')->nullable();

                $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $t->timestamps();
                $t->index(['ko_object_id', 'jenis', 'tanggal']);
            });
        }

        // ── Tindak lanjut dari peringatan ──
        if (!Schema::hasTable('ko_actions')) {
            Schema::create('ko_actions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('ko_object_id')->constrained('ko_objects')->cascadeOnDelete();
                $t->foreignId('ko_safeguard_id')->nullable()->constrained('ko_safeguards')->nullOnDelete();

                $t->string('sumber');                       // Sertifikat | Pengaman | Perawatan | Kajian | Manual
                $t->text('uraian');
                $t->string('prioritas')->default('Sedang'); // Tinggi | Sedang | Rendah
                $t->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('pic_nama')->nullable();
                $t->date('target_tgl')->nullable();
                $t->string('status')->default('Terbuka');   // Terbuka | Berjalan | Selesai | Dibatalkan
                $t->date('tgl_selesai')->nullable();
                $t->text('tindakan')->nullable();

                $t->timestamps();
                $t->index(['ko_object_id', 'status']);
            });
        }

        // ── Tautan & lampiran pada tabel yang sudah ada ──
        Schema::table('ko_objects', function (Blueprint $t) {
            if (!Schema::hasColumn('ko_objects', 'lampiran'))   $t->string('lampiran')->nullable();
            if (!Schema::hasColumn('ko_objects', 'keterangan')) $t->text('keterangan')->nullable();
        });

        Schema::table('ko_reviews', function (Blueprint $t) {
            if (!Schema::hasColumn('ko_reviews', 'ko_personnel_id')) {
                $t->foreignId('ko_personnel_id')->nullable()->constrained('ko_personnel')->nullOnDelete();
            }
            if (!Schema::hasColumn('ko_reviews', 'lampiran')) $t->string('lampiran')->nullable();
            if (!Schema::hasColumn('ko_reviews', 'ringkasan')) $t->text('ringkasan')->nullable();
            // pemicu insiden dari modul Hazard — tanpa foreign key agar tidak terikat urutan pasang
            if (!Schema::hasColumn('ko_reviews', 'hazard_report_id')) {
                $t->unsignedBigInteger('hazard_report_id')->nullable()->index();
            }
        });

        Schema::table('ko_personnel', function (Blueprint $t) {
            if (!Schema::hasColumn('ko_personnel', 'user_id')) {
                $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            }
        });

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'ko_role')) {
            Schema::table('users', fn (Blueprint $t) => $t->string('ko_role')->nullable());
        }

        // ── Ambang & target bawaan ──
        if (Schema::hasTable('app_settings')) {
            foreach ([
                'ko_warn_days'     => 90,
                'ko_target_layak'  => 95,
                'ko_target_pmc'    => 90,
                'ko_iv_peralatan'  => 3,
                'ko_iv_instalasi'  => 5,
            ] as $k => $v) {
                DB::table('app_settings')->updateOrInsert(
                    ['key' => $k],
                    ['value' => json_encode($v), 'updated_at' => now(), 'created_at' => now()]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ko_actions');
        Schema::dropIfExists('ko_inspections');
        Schema::dropIfExists('ko_safeguards');

        Schema::table('ko_objects', function (Blueprint $t) {
            foreach (['lampiran', 'keterangan'] as $c) {
                if (Schema::hasColumn('ko_objects', $c)) $t->dropColumn($c);
            }
        });

        Schema::table('ko_reviews', function (Blueprint $t) {
            if (Schema::hasColumn('ko_reviews', 'ko_personnel_id')) $t->dropConstrainedForeignId('ko_personnel_id');
            foreach (['lampiran', 'ringkasan', 'hazard_report_id'] as $c) {
                if (Schema::hasColumn('ko_reviews', $c)) $t->dropColumn($c);
            }
        });

        Schema::table('ko_personnel', function (Blueprint $t) {
            if (Schema::hasColumn('ko_personnel', 'user_id')) $t->dropConstrainedForeignId('user_id');
        });

        if (Schema::hasColumn('users', 'ko_role')) {
            Schema::table('users', fn (Blueprint $t) => $t->dropColumn('ko_role'));
        }

        if (Schema::hasTable('app_settings')) {
            DB::table('app_settings')->whereIn('key', [
                'ko_warn_days', 'ko_target_layak', 'ko_target_pmc', 'ko_iv_peralatan', 'ko_iv_instalasi',
            ])->delete();
        }
    }
};
