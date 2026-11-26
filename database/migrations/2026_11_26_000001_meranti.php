<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MERANTI — Monitoring, Evaluation of Reclamation and Nursery Information.
 *
 * Aplikasi pembibitan, penanaman, dan kepatuhan reklamasi yang semula
 * berjalan di Google Apps Script/Supabase, dipindahkan ke EQOHSEE.
 * Aslinya satu lembar (atau satu baris JSON) per modul; di sini setiap
 * modul mendapat tabelnya sendiri dengan kolom bertipe dan company_id,
 * mengikuti App\Support\Meranti\Katalog.
 *
 * `asal_ref` mencatat asal baris hasil impor lembar Google Sheets
 * ("stok_bibit#7"), sehingga impor ulang memperbarui baris yang sama
 * alih-alih menggandakannya. Baris yang diketik di EQOHSEE membiarkannya
 * kosong.
 */
return new class extends Migration
{
    /** @var array<string, array{0: callable(Blueprint): void, 1: list<string>}> */
    private function tabel(): array
    {
        return [
            'meranti_tanaman' => [function (Blueprint $t) {
                $t->string('nama_tanaman', 150);
                $t->string('nama_latin', 150)->nullable();
                $t->string('golongan', 30)->nullable();
            }, ['golongan']],
            'meranti_stok_bibit' => [function (Blueprint $t) {
                $t->string('nama_bibit', 150);
                $t->integer('stok')->nullable();
                $t->string('golongan', 30)->nullable();
            }, ['nama_bibit']],
            'meranti_bibit_masuk' => [function (Blueprint $t) {
                $this->mutasiBibit($t);
                $t->string('sumber', 150)->nullable();
                $t->string('keterangan', 500)->nullable();
            }, ['tanggal']],
            'meranti_bibit_keluar' => [function (Blueprint $t) {
                $this->mutasiBibit($t);
                $t->string('tujuan', 150)->nullable();
                $t->string('keterangan', 500)->nullable();
            }, ['tanggal']],
            'meranti_bibit_kembali' => [function (Blueprint $t) {
                $this->mutasiBibit($t);
                $t->string('keterangan', 500)->nullable();
            }, ['tanggal']],
            'meranti_bibit_mati' => [function (Blueprint $t) {
                $this->mutasiBibit($t);
                $t->string('penyebab', 190)->nullable();
            }, ['tanggal']],
            'meranti_bibit_rusak' => [function (Blueprint $t) {
                $this->mutasiBibit($t);
                $t->string('penyebab', 190)->nullable();
            }, ['tanggal']],
            'meranti_stok_material' => [function (Blueprint $t) {
                $t->string('nama_material', 150);
                $t->decimal('material_stok', 14, 2)->nullable();
                $t->string('nama_satuan', 30)->nullable();
            }, ['nama_material']],
            'meranti_material_masuk' => [function (Blueprint $t) {
                $this->mutasiMaterial($t);
                $t->string('sumber', 150)->nullable();
            }, ['tanggal']],
            'meranti_material_keluar' => [function (Blueprint $t) {
                $this->mutasiMaterial($t);
                $t->string('tujuan', 150)->nullable();
            }, ['tanggal']],
            'meranti_progres' => [function (Blueprint $t) {
                $t->unsignedSmallInteger('tahun');
                $t->string('bulan', 12);
                $t->string('minggu', 12);
                $t->date('tanggal')->nullable();
                $t->string('pelaksana', 150)->nullable();
                $t->string('pekerjaan', 4)->nullable();             // P0, P1, P2
                $t->string('nama_blok', 100);
                $t->decimal('luas_realisasi', 12, 4)->nullable();
                $t->string('keterangan', 500)->nullable();
            }, ['tahun']],
            'meranti_target_blok' => [function (Blueprint $t) {
                $t->string('nama_blok', 100);
                $t->string('pekerjaan', 4);
                $t->decimal('luas_target', 12, 4);
                $t->unsignedSmallInteger('tahun')->nullable();
                $t->string('keterangan', 500)->nullable();
            }, ['tahun']],
            'meranti_arsip_capaian' => [function (Blueprint $t) {
                $t->string('nama_project', 150);
                $t->string('kategori_pelaksana', 20)->nullable();
                $t->string('pelaksana', 150)->nullable();
                $t->unsignedSmallInteger('tahun')->nullable();
                $t->string('bulan', 12)->nullable();
                $t->string('week', 20)->nullable();
                $t->string('file', 500)->nullable();
                $t->string('penginput', 100)->nullable();
            }, ['tahun']],
            'meranti_penanaman' => [function (Blueprint $t) {
                $t->date('tanggal');
                $t->string('nama_bibit', 150);
                $t->string('golongan', 30)->nullable();
                $t->integer('jumlah')->nullable();
                $t->string('nama_project', 150)->nullable();
                $t->string('nama_kegiatan', 190)->nullable();
                $t->string('pelaksana', 150)->nullable();
                $t->string('nama_blok', 100)->nullable();
                $t->string('penginput', 100)->nullable();
            }, ['tanggal']],
            'meranti_rencana' => [function (Blueprint $t) {
                $t->unsignedSmallInteger('tahun');
                $t->decimal('luas_bukaan_ha', 12, 4)->nullable();
                $t->decimal('luas_reklamasi_ha', 12, 4)->nullable();
                $t->integer('jumlah_bibit_rencana')->nullable();
                $t->decimal('biaya_rencana', 18, 2)->nullable();
                $t->string('keterangan', 500)->nullable();
            }, ['tahun']],
            'meranti_penilaian' => [function (Blueprint $t) {
                $t->string('blok', 100);
                $t->unsignedSmallInteger('tahun')->nullable();
                $t->decimal('nilai_penatagunaan', 6, 2)->nullable();
                $t->decimal('nilai_revegetasi', 6, 2)->nullable();
                $t->decimal('nilai_penyelesaian', 6, 2)->nullable();
                $t->decimal('skor_total', 6, 2)->nullable();
                $t->string('kategori', 20)->nullable();
            }, ['tahun']],
            'meranti_jaminan' => [function (Blueprint $t) {
                $t->string('periode', 60);
                $t->string('jenis', 40)->nullable();
                $t->decimal('nilai', 18, 2)->nullable();
                $t->date('tanggal_penempatan')->nullable();
                $t->string('status', 30)->nullable();
            }, ['status']],
            'meranti_surat' => [function (Blueprint $t) {
                $t->string('nomor_surat', 120);
                $t->string('jenis', 10)->nullable();
                $t->date('tanggal')->nullable();
                $t->string('perihal', 255)->nullable();
                $t->string('dari_kepada', 190)->nullable();
            }, ['tanggal']],
            'meranti_pengajuan_dana' => [function (Blueprint $t) {
                $t->string('nomor', 120);
                $t->string('project', 150)->nullable();
                $t->string('periode', 100)->nullable();
                $t->decimal('nilai', 18, 2)->nullable();
                $t->string('status', 20)->nullable();
            }, ['status']],
            'meranti_berita_acara' => [function (Blueprint $t) {
                $t->string('nomor', 120);
                $t->string('judul', 255)->nullable();
                $t->date('tanggal')->nullable();
                $t->string('terkait', 190)->nullable();
            }, ['tanggal']],
            'meranti_periode' => [function (Blueprint $t) {
                $t->string('nama_periode', 150);
                $t->date('tanggal_mulai')->nullable();
                $t->date('tanggal_selesai')->nullable();
                $t->string('status', 10)->nullable();
            }, ['status']],
            'meranti_project' => [function (Blueprint $t) {
                $t->string('kode_project', 40)->nullable();
                $t->string('nama_project', 150);
                $t->string('keterangan', 500)->nullable();
            }, ['nama_project']],
            'meranti_pelaksana' => [function (Blueprint $t) {
                $t->string('nama_pelaksana', 150);
                $t->string('kategori', 20)->nullable();
            }, ['nama_pelaksana']],
            'meranti_project_vendor' => [function (Blueprint $t) {
                $t->string('kode_project_vendor', 40);
                $t->string('nama_vendor', 150)->nullable();
                $t->string('nama_project', 150)->nullable();
            }, ['kode_project_vendor']],
            'meranti_peta' => [function (Blueprint $t) {
                $t->string('nama_fitur', 150);
                $t->string('kategori', 30)->nullable();
                $t->string('petak', 60)->nullable();
                $t->string('week', 30)->nullable();
                $t->string('month', 20)->nullable();
                $t->unsignedSmallInteger('year')->nullable();
                $t->decimal('luas_ha', 14, 6)->nullable();
                $t->longText('geojson')->nullable();                 // satu geometri GeoJSON per baris
                $t->string('sumber_file', 190)->nullable();
            }, ['kategori']],
        ];
    }

    private function mutasiBibit(Blueprint $t): void
    {
        $t->date('tanggal');
        $t->string('nama_bibit', 150);
        $t->integer('jumlah')->nullable();
    }

    private function mutasiMaterial(Blueprint $t): void
    {
        $t->date('tanggal');
        $t->string('nama_material', 150);
        $t->decimal('jumlah', 14, 2)->nullable();
        $t->string('satuan', 30)->nullable();
    }

    public function up(): void
    {
        foreach ($this->tabel() as $nama => [$kolom, $indeks]) {
            Schema::create($nama, function (Blueprint $t) use ($kolom, $indeks) {
                $t->id();
                $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
                $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
                $kolom($t);
                $t->string('asal_ref', 80)->nullable();
                $t->timestamps();
                $t->unique(['company_id', 'asal_ref']);
                $t->index(array_merge(['company_id'], $indeks));
            });
        }

        // Log aktivitas MERANTI — termasuk riwayat dari lembar Google Sheets.
        Schema::create('meranti_log', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->dateTime('waktu');
            $t->string('pengguna', 100)->nullable();
            $t->string('aksi', 20);                               // Tambah, Ubah, Hapus, Impor, Login, …
            $t->string('modul', 100)->nullable();
            $t->text('detail')->nullable();
            $t->string('asal_ref', 80)->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'asal_ref']);
            $t->index(['company_id', 'waktu']);
        });

        // Setelan per perusahaan: folder Google Drive tempat berkas capaian besar.
        Schema::create('meranti_setelan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('folder_drive', 500)->nullable();
            $t->string('catatan', 500)->nullable();
            $t->timestamps();
            $t->unique(['company_id']);
        });

        if (!Schema::hasColumn('users', 'meranti_role')) {
            Schema::table('users', function (Blueprint $t) {
                $t->string('meranti_role', 20)->nullable()->after('energi_role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'meranti_role')) {
            Schema::table('users', function (Blueprint $t) {
                $t->dropColumn('meranti_role');
            });
        }

        Schema::dropIfExists('meranti_setelan');
        Schema::dropIfExists('meranti_log');

        foreach (array_reverse(array_keys($this->tabel())) as $nama) {
            Schema::dropIfExists($nama);
        }
    }
};
