<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skema modul audit SMKP diganti total mengikuti Aurorasys — bagian
 * TAMBAHAN saja (kolom & tabel baru, nama kolom bukti diseragamkan).
 *
 * Tidak ada data yang dibuang di sini. Pemindahan isi lama ke bentuk baru,
 * pengarsipan yang tidak punya padanan, lalu pembuangan kolom & tabel lama
 * dikerjakan migrasi berikutnya (2026_11_07_000003_pindah_data_smkp_aurorasys)
 * sesudah seluruh isinya tersalin.
 *
 * Bentuk JSON tiap kolom: lihat App\Models\SmkpAudit dan
 * App\Http\Controllers\SmkpController.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ── smkp_audits: paket formulir, tanda tangan, hari kerja beku ── */
        Schema::table('smkp_audits', function (Blueprint $t) {
            foreach (['kesesuaian', 'rekomendasi', 'ttd_formulir', 'mandays_tetap'] as $k) {
                if (!Schema::hasColumn('smkp_audits', $k)) $t->json($k)->nullable();
            }
        });

        /* ── smkp_findings: isian Formulir Ketidaksesuaian ── */
        Schema::table('smkp_findings', function (Blueprint $t) {
            if (!Schema::hasColumn('smkp_findings', 'nomor'))      $t->string('nomor', 40)->nullable();
            if (!Schema::hasColumn('smkp_findings', 'lokasi'))     $t->string('lokasi', 200)->nullable();
            if (!Schema::hasColumn('smkp_findings', 'divisi'))     $t->string('divisi', 190)->nullable();
            if (!Schema::hasColumn('smkp_findings', 'departemen')) $t->string('departemen', 190)->nullable();
            if (!Schema::hasColumn('smkp_findings', 'bukti'))      $t->text('bukti')->nullable();
            if (!Schema::hasColumn('smkp_findings', 'deskripsi'))  $t->text('deskripsi')->nullable();
            if (!Schema::hasColumn('smkp_findings', 'bukti_perbaikan'))     $t->text('bukti_perbaikan')->nullable();
            if (!Schema::hasColumn('smkp_findings', 'nomor_tindak_lanjut')) $t->string('nomor_tindak_lanjut', 40)->nullable();
        });
        if (!Schema::hasIndex('smkp_findings', ['audit_id', 'status'])) {
            Schema::table('smkp_findings', fn (Blueprint $t) => $t->index(['audit_id', 'status']));
        }
        if (!Schema::hasIndex('smkp_attendees', ['audit_id', 'rapat'])) {
            Schema::table('smkp_attendees', fn (Blueprint $t) => $t->index(['audit_id', 'rapat']));
        }

        /* ── smkp_bukti: nama kolom Aurorasys + alur verifikasi ── */
        $ganti = ['file_path' => 'path', 'file_name' => 'nama', 'file_size' => 'ukuran', 'catatan' => 'keterangan'];
        foreach ($ganti as $lama => $baru) {
            if (Schema::hasColumn('smkp_bukti', $lama) && !Schema::hasColumn('smkp_bukti', $baru)) {
                Schema::table('smkp_bukti', fn (Blueprint $t) => $t->renameColumn($lama, $baru));
            }
        }
        // Keterangan pengunggah sampai 500 karakter (kolom lama 255).
        Schema::table('smkp_bukti', fn (Blueprint $t) => $t->string('keterangan', 500)->nullable()->change());
        Schema::table('smkp_bukti', function (Blueprint $t) {
            if (!Schema::hasColumn('smkp_bukti', 'status'))   $t->string('status', 16)->default('menunggu');
            if (!Schema::hasColumn('smkp_bukti', 'catatan'))  $t->string('catatan', 1000)->nullable();
            if (!Schema::hasColumn('smkp_bukti', 'diverifikasi_oleh')) {
                $t->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('smkp_bukti', 'diverifikasi_pada')) $t->timestamp('diverifikasi_pada')->nullable();
        });
        foreach ([['audit_id', 'kode'], ['audit_id', 'status'], ['user_id', 'created_at']] as $kolom) {
            if (!Schema::hasIndex('smkp_bukti', $kolom)) {
                Schema::table('smkp_bukti', fn (Blueprint $t) => $t->index($kolom));
            }
        }

        /* ── kiriman tindak lanjut auditi ── */
        if (!Schema::hasTable('smkp_tindak_lanjut')) {
            Schema::create('smkp_tindak_lanjut', function (Blueprint $t) {
                $t->id();
                $t->foreignId('finding_id')->constrained('smkp_findings')->cascadeOnDelete();
                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->text('akar_masalah')->nullable();
                $t->text('tindakan');
                $t->text('bukti_perbaikan')->nullable();
                $t->string('penanggung_jawab', 150)->nullable();
                $t->json('lampiran')->nullable();             // [{path, nama, mime, ukuran}]
                $t->timestamps();
                $t->index(['finding_id', 'id']);
            });
        }

        /* ── pengingat email batas waktu tindak lanjut ── */
        if (!Schema::hasTable('smkp_pengingat')) {
            Schema::create('smkp_pengingat', function (Blueprint $t) {
                $t->id();
                $t->foreignId('finding_id')->constrained('smkp_findings')->cascadeOnDelete();
                $t->date('target');                           // target_selesai saat pengingat diproses
                $t->string('jadwal', 8);                      // 2b = 2 bulan, 1m = 1 minggu sebelum target
                $t->text('penerima')->nullable();             // email penerima, dipisah koma
                $t->timestamp('dikirim_pada')->nullable();    // kosong: dilewati karena tersusul jadwal yang lebih dekat
                $t->timestamps();
                $t->unique(['finding_id', 'target', 'jadwal']);
            });
        }

        /*
         * ── arsip isian SMKP lama ──
         *
         * Satu baris per baris lama yang diubah atau dibuang migrasi
         * pemindahan: salinan utuh baris aslinya (`asli`) dan ruas yang
         * tidak punya padanan di skema baru (`tanpa_padanan`), supaya tidak
         * ada isian yang hilang dan migrasinya dapat dibalik. Juga mencatat
         * baris yang DIBUAT migrasi (jenis 'dibuat') dan berkas yang
         * disalin (jenis 'berkas'). Tidak dibaca aplikasi.
         */
        if (!Schema::hasTable('smkp_arsip_eqohsee')) {
            Schema::create('smkp_arsip_eqohsee', function (Blueprint $t) {
                $t->id();
                $t->string('tabel', 40);
                $t->unsignedBigInteger('baris_id')->nullable();
                $t->unsignedBigInteger('audit_id')->nullable();
                $t->string('jenis', 20);                      // asli | dibuat | berkas | catatan
                $t->json('asli')->nullable();
                $t->json('tanpa_padanan')->nullable();
                $t->text('catatan')->nullable();
                $t->timestamps();
                $t->index(['tabel', 'baris_id']);
                $t->index('audit_id');
            });
        }
    }

    public function down(): void
    {
        /* MySQL dapat membuang diam-diam indeks yang dibuatnya sendiri untuk
           kunci asing begitu ada indeks gabungan berawalan kolom yang sama;
           indeks gabungan itu lalu tidak dapat dibuang. Indeks tunggalnya
           dipastikan ada lebih dulu. */
        $this->pastikanIndeks('smkp_bukti', 'audit_id');
        $this->pastikanIndeks('smkp_bukti', 'user_id');
        $this->pastikanIndeks('smkp_findings', 'audit_id');
        $this->pastikanIndeks('smkp_attendees', 'audit_id');

        Schema::dropIfExists('smkp_arsip_eqohsee');
        Schema::dropIfExists('smkp_pengingat');
        Schema::dropIfExists('smkp_tindak_lanjut');

        foreach ([['user_id', 'created_at'], ['audit_id', 'status'], ['audit_id', 'kode']] as $kolom) {
            if (Schema::hasIndex('smkp_bukti', $kolom)) {
                Schema::table('smkp_bukti', fn (Blueprint $t) => $t->dropIndex($kolom));
            }
        }
        if (Schema::hasColumn('smkp_bukti', 'diverifikasi_oleh')) {
            Schema::table('smkp_bukti', function (Blueprint $t) {
                $t->dropForeign(['diverifikasi_oleh']);
            });
        }
        Schema::table('smkp_bukti', function (Blueprint $t) {
            $t->dropColumn(array_values(array_filter(['status', 'catatan', 'diverifikasi_oleh', 'diverifikasi_pada'],
                fn ($k) => Schema::hasColumn('smkp_bukti', $k))));
        });
        foreach (['path' => 'file_path', 'nama' => 'file_name', 'ukuran' => 'file_size', 'keterangan' => 'catatan'] as $baru => $lama) {
            if (Schema::hasColumn('smkp_bukti', $baru) && !Schema::hasColumn('smkp_bukti', $lama)) {
                Schema::table('smkp_bukti', fn (Blueprint $t) => $t->renameColumn($baru, $lama));
            }
        }

        if (Schema::hasIndex('smkp_attendees', ['audit_id', 'rapat'])) {
            Schema::table('smkp_attendees', fn (Blueprint $t) => $t->dropIndex(['audit_id', 'rapat']));
        }
        if (Schema::hasIndex('smkp_findings', ['audit_id', 'status'])) {
            Schema::table('smkp_findings', fn (Blueprint $t) => $t->dropIndex(['audit_id', 'status']));
        }
        Schema::table('smkp_findings', function (Blueprint $t) {
            $t->dropColumn(array_values(array_filter(
                ['nomor', 'lokasi', 'divisi', 'departemen', 'bukti', 'deskripsi', 'bukti_perbaikan', 'nomor_tindak_lanjut'],
                fn ($k) => Schema::hasColumn('smkp_findings', $k))));
        });
        Schema::table('smkp_audits', function (Blueprint $t) {
            $t->dropColumn(array_values(array_filter(['kesesuaian', 'rekomendasi', 'ttd_formulir', 'mandays_tetap'],
                fn ($k) => Schema::hasColumn('smkp_audits', $k))));
        });
    }

    private function pastikanIndeks(string $tabel, string $kolom): void
    {
        if (!Schema::hasIndex($tabel, [$kolom])) {
            Schema::table($tabel, fn (Blueprint $t) => $t->index($kolom));
        }
    }
};
