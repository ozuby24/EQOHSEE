<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Empat tabel yang sama sekali tidak menyebut perusahaan.
 *
 * Berbeda dari migrasi 000008, yang melonggarkan indeks unik pada tabel
 * yang SUDAH punya company_id. Di sini kolomnya memang tidak ada, dan
 * akibatnya lebih berat daripada penyimpanan yang gagal:
 *
 *   energy_production   — produksi harian, `tanggal` unik se-pemasangan
 *   energy_fuel_recon   — rekonsiliasi tangki, `tanggal` unik se-pemasangan
 *   energy_other_logs   — pemakaian energi lain
 *   tpkkp_assessments   — penilaian TPKKP, `tahun` unik se-pemasangan
 *
 * Dua kegagalan sekaligus, dan yang pertama lebih berbahaya:
 *
 * 1. TERBACA SILANG. Produksi harian satu perusahaan tampil pada
 *    halaman perusahaan lain, dan ikut menjadi pembagi intensitas
 *    energinya. Angkanya tetap berupa persentase yang wajar; tidak ada
 *    apa pun di layar yang mengatakan datanya milik orang lain.
 *
 * 2. PERUSAHAAN KEDUA TIDAK DAPAT MENCATAT. `tanggal` dan `tahun` unik
 *    se-pemasangan berarti begitu satu perusahaan mencatat produksi 16
 *    Agustus, tidak ada perusahaan lain yang dapat mencatat hari itu —
 *    dan pesannya hanya "UNIQUE constraint failed", pada penyimpanan
 *    yang datanya sudah benar.
 *
 * Ketahuan dari uji cakupan data contoh: sesudah data contoh dibuang,
 * baris ketiga tabel energi itu tertinggal — penghapusnya bekerja
 * dengan menyebut company_id, dan kolom itu tidak ada untuk disebut.
 *
 * BARIS LAMA DIBERI PEMILIK, tidak dibiarkan kosong. Baris tanpa
 * perusahaan tetap terbaca oleh semua perusahaan di bawah scope
 * MilikPerusahaan — persis kebocoran yang sedang ditutup. Bila
 * pemasangan ini hanya punya satu perusahaan, itulah pemiliknya; bila
 * lebih dari satu, tidak ada jawaban yang dapat ditebak dengan aman,
 * jadi barisnya dibiarkan dan dilaporkan Diagnosa lewat pemeriksaan
 * "Tanpa pemilik".
 */
return new class extends Migration
{
    /**
     * tabel => indeks unik lama yang harus disertai company_id
     *
     * @var array<string,array{0:string,1:list<string>}|null>
     */
    private const TABEL = [
        'energy_production' => ['energy_production_tanggal_unique', ['tanggal']],
        'energy_fuel_recon' => ['energy_fuel_recon_tanggal_unique', ['tanggal']],
        'energy_other_logs' => null,
        'tpkkp_assessments' => ['tpkkp_assessments_tahun_unique',   ['tahun']],
    ];

    public function up(): void
    {
        /* Satu-satunya perusahaan, bila memang hanya ada satu. */
        $tunggal = Schema::hasTable('companies') && DB::table('companies')->count() === 1
            ? DB::table('companies')->value('id')
            : null;

        foreach (self::TABEL as $tabel => $unik) {
            if (!Schema::hasTable($tabel)) continue;

            if (!Schema::hasColumn($tabel, 'company_id')) {
                Schema::table($tabel, function (Blueprint $t) {
                    $t->foreignId('company_id')->nullable()->after('id')
                      ->constrained()->nullOnDelete();
                });

                if ($tunggal !== null) {
                    DB::table($tabel)->whereNull('company_id')->update(['company_id' => $tunggal]);
                }
            }

            if ($unik === null) continue;

            [$lama, $kolom] = $unik;

            Schema::table($tabel, function (Blueprint $t) use ($tabel, $lama, $kolom) {
                /* Indeks lama mungkin sudah tidak ada — dibuat tangan,
                   dibuang migrasi lain, atau bernama lain pada penggerak
                   yang berbeda. Gagal membuangnya bukan alasan
                   membatalkan sisanya. */
                try { $t->dropUnique($lama); } catch (\Throwable) {}

                $t->unique(array_merge(['company_id'], $kolom), $tabel.'_company_unik');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel => $unik) {
            if (!Schema::hasTable($tabel)) continue;

            if ($unik !== null) {
                Schema::table($tabel, function (Blueprint $t) use ($tabel, $unik) {
                    try { $t->dropUnique($tabel.'_company_unik'); } catch (\Throwable) {}

                    /* Sengaja TIDAK memasang kembali indeks unik lama:
                       sesudah dua perusahaan mencatat tanggal yang sama,
                       tidak ada lagi yang dapat memenuhinya, dan
                       migrasinya akan gagal di tengah jalan. */
                });
            }

            if (Schema::hasColumn($tabel, 'company_id')) {
                Schema::table($tabel, function (Blueprint $t) {
                    $t->dropConstrainedForeignId('company_id');
                });
            }
        }
    }
};
