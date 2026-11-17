<?php

use Illuminate\Database\Migrations\Migration;
use App\Support\Indeks;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Indeks untuk batas perusahaan.
 *
 * Enam belas tabel membawa company_id tanpa satu pun indeks yang diawali
 * kolom itu. Padahal MilikPerusahaan menambahkan `where company_id = ?`
 * pada SETIAP kueri ke tabel-tabel itu — scope global, bukan pilihan per
 * kueri — sehingga setiap pembacaan memindai seluruh tabel lalu membuang
 * baris milik perusahaan lain.
 *
 * Yang membuatnya tidak pernah terasa: pemindaian penuh atas dua ratus
 * baris memang cepat. Tabel-tabel ini justru yang paling deras isinya —
 * catatan air harian, bacaan instrumen lereng, pemantauan lingkungan,
 * muatan angkutan, pemeriksaan gas izin kerja. Semuanya tumbuh tiap hari
 * dan tidak pernah dipangkas.
 *
 * Akibatnya melambat perlahan sekali. Tidak ada satu hari pun ketika
 * halamannya "menjadi lambat"; ia hanya sedikit lebih lambat tiap bulan
 * daripada bulan sebelumnya, dan pada saat ada yang mengeluh, tidak ada
 * perubahan yang dapat ditunjuk sebagai sebabnya. Justru pemasangan yang
 * PALING lama dipakai — yang datanya paling berharga — yang paling
 * terpukul.
 *
 * Yang punya kolom tanggal diberi indeks gabungan (company_id, tanggal).
 * Hampir seluruh halamannya menyaring keduanya sekaligus: milik
 * perusahaan ini, pada rentang waktu ini. Indeks gabungan melayani kedua
 * syarat dalam satu telusuran; dua indeks terpisah memaksa basis data
 * memilih salah satunya lalu menyaring sisanya dengan tangan.
 */
return new class extends Migration
{
    /** tabel => kolom pendamping sesudah company_id, atau null. */
    private const TABEL = [
        'users'                      => null,
        'certificates'               => 'issued_at',
        'ko_personnel'               => null,
        'energy_opportunities'       => null,

        /* Terlewat pada pemindaian pertama: pemindaiannya dijalankan
           terhadap basis data pengembangan yang beberapa migrasinya
           belum sempat berjalan, sehingga tabel ini belum ada di sana.
           Ditemukan oleh MigrasiIndeksTest, yang memindai skema HASIL
           migrasi penuh — satu-satunya skema yang benar-benar mewakili
           yang akan berjalan di server. */
        'energy_other_logs'          => 'tanggal',
        'mine_map_layers'            => null,
        'mine_operational_records'   => 'tanggal',
        'konservasi_minerba_records' => 'periode',
        'water_logs'                 => 'tanggal',
        'geo_bacaans'                => 'tanggal',
        'reklamasi_kemajuans'        => 'tanggal',
        'lingkungan_pantaus'         => 'tanggal',
        'ledak_hasils'               => null,
        'ledak_ukurs'                => null,
        'angkut_muatans'             => null,
        'izin_periksas'              => null,
        'izin_gas'                   => null,
    ];

    public function up(): void
    {
        foreach (self::TABEL as $tabel => $pendamping) {
            /* Diperiksa satu per satu, bukan diandaikan ada.
               Daftar ini diambil dari skema yang berjalan pada satu
               pemasangan; pemasangan lain bisa tertinggal beberapa
               migrasi, dan migrasi yang gagal di tengah meninggalkan
               basis data dalam keadaan separuh — lebih sulit dipulihkan
               daripada indeks yang belum sempat dibuat. */
            if (!Schema::hasTable($tabel)) continue;
            if (!Schema::hasColumn($tabel, 'company_id')) continue;

            $kolom = ['company_id'];
            if ($pendamping !== null && Schema::hasColumn($tabel, $pendamping)) {
                $kolom[] = $pendamping;
            }

            $nama = $tabel.'_'.implode('_', $kolom).'_index';

            if (Indeks::ada($tabel, $nama)) continue;

            Schema::table($tabel, fn (Blueprint $t) => $t->index($kolom, $nama));
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel => $pendamping) {
            if (!Schema::hasTable($tabel)) continue;

            $kolom = ['company_id'];
            if ($pendamping !== null && Schema::hasColumn($tabel, $pendamping)) {
                $kolom[] = $pendamping;
            }

            $nama = $tabel.'_'.implode('_', $kolom).'_index';

            if (!Indeks::ada($tabel, $nama)) continue;

            Schema::table($tabel, fn (Blueprint $t) => $t->dropIndex($nama));
        }
    }

};
