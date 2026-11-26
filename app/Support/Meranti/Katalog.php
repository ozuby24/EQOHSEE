<?php

namespace App\Support\Meranti;

use App\Models\Meranti as M;
use Illuminate\Validation\Rule;

/**
 * Katalog modul MERANTI — Monitoring, Evaluation of Reclamation and
 * Nursery Information.
 *
 * MERANTI aslinya aplikasi yang digerakkan konfigurasi: satu daftar modul
 * beserta kolomnya, lalu satu tabel dan satu formulir generik yang
 * membacanya. Bentuk itu dipertahankan di sini karena memang tepat untuk
 * dua puluh lima daftar sederhana yang hanya berbeda kolom. Bedanya,
 * setiap modul di EQOHSEE punya TABELNYA SENDIRI dengan kolom bertipe dan
 * company_id — bukan satu tabel JSON bersama — supaya batas perusahaan,
 * indeks, dan data contoh bekerja seperti modul lain.
 *
 * Katalog ini satu-satunya tempat yang menyebut kolom sebuah modul:
 * migrasi, formulir, validasi, ekspor CSV, dan impor lembar Google Sheets
 * semuanya membacanya dari sini.
 *
 * Tipe kolom: teks, panjang (area teks), angka, tanggal, pilih, tautan,
 * hitung (diisi server, tidak dapat diketik).
 */
final class Katalog
{
    public const GOLONGAN = ['MPTS', 'Pioneer', 'Lokal', 'Cover Crop'];
    public const BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    public const MINGGU = ['Minggu 1', 'Minggu 2', 'Minggu 3', 'Minggu 4', 'Minggu 5'];
    public const PEKERJAAN = ['P0', 'P1', 'P2'];
    public const LABEL_PEKERJAAN = ['P0' => 'Penanaman', 'P1' => 'Pemeliharaan I', 'P2' => 'Pemeliharaan II'];
    public const SATUAN = ['Kg', 'Gram', 'Pack', 'Roll', 'Pcs', 'Botol', 'Liter'];
    public const KATEGORI_PELAKSANA = ['Vendor', 'In House'];
    public const KATEGORI_PETA = ['Blok', 'Petak', 'Batas Area', 'Titik Tanam', 'Lainnya'];

    private const PETUNJUK_P = 'P0 Penanaman · P1 Pemeliharaan I · P2 Pemeliharaan II';

    /** Urutan kelompok menu, sama dengan MERANTI. */
    public const GRUP = ['Nursery', 'Material Stok', 'Rehabilitasi DAS', 'Reklamasi', 'Administrasi', 'Data Master'];

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $cache = null;

    /** @return array<string, array<string, mixed>> */
    public static function semua(): array
    {
        return self::$cache ??= self::susun();
    }

    /** @return array<string, mixed>|null */
    public static function modul(string $kunci): ?array
    {
        return self::semua()[$kunci] ?? null;
    }

    public static function ada(string $kunci): bool
    {
        return isset(self::semua()[$kunci]);
    }

    /** @return list<string> */
    public static function kunci(): array
    {
        return array_keys(self::semua());
    }

    /**
     * Kolom yang dapat diisi lewat formulir (tanpa kolom hitung).
     *
     * @return list<array<string, mixed>>
     */
    public static function kolomIsian(string $kunci): array
    {
        return array_values(array_filter(self::modul($kunci)['kolom'] ?? [], fn ($k) => $k['t'] !== 'hitung'));
    }

    /** @return list<string> */
    public static function namaKolom(string $kunci): array
    {
        return array_column(self::modul($kunci)['kolom'] ?? [], 'k');
    }

    /**
     * Aturan validasi formulir sebuah modul.
     *
     * Pilihan diperiksa terhadap daftarnya; angka dan tanggal terhadap
     * tipenya. Kolom wajib mengikuti tanda `wajib` di katalog — sama
     * dengan tanda bintang di formulir MERANTI.
     *
     * @return array<string, mixed>
     */
    public static function aturan(string $kunci): array
    {
        $aturan = [];

        foreach (self::kolomIsian($kunci) as $k) {
            $r = [$k['wajib'] ?? false ? 'required' : 'nullable'];

            switch ($k['t']) {
                case 'angka':
                    $r[] = 'numeric';
                    $r[] = 'min:'.($k['min'] ?? 0);
                    if (isset($k['maks'])) $r[] = 'max:'.$k['maks'];
                    break;
                case 'tanggal':
                    $r[] = 'date';
                    break;
                case 'pilih':
                    $r[] = Rule::in($k['opsi']);
                    break;
                case 'tautan':
                    $r[] = 'string';
                    $r[] = 'max:500';
                    break;
                case 'panjang':
                    $r[] = 'string';
                    $r[] = 'max:'.($k['panjang'] ?? 5000);
                    break;
                default:
                    $r[] = 'string';
                    $r[] = 'max:'.($k['panjang'] ?? 190);
            }

            $aturan[$k['k']] = $r;
        }

        return $aturan;
    }

    /**
     * Modul yang mengisi sebuah lembar Google Sheets MERANTI.
     *
     * Nama lembarnya dapat berawalan "MERANTI - " (versi Apps Script lama)
     * dan dapat TERPOTONG: Excel membatasi nama lembar 31 karakter, jadi
     * "MERANTI - Penilaian Keberhasilan" tiba sebagai "MERANTI - Penilaian
     * Keberhasila". Karena itu pencocokannya awalan, bukan persamaan.
     */
    public static function modulUntukLembar(string $lembar): ?string
    {
        // Dibandingkan tanpa tanda baca: peramban mengganti "/" pada nama berkas
        // unduhan ("Surat_Keluar/Masuk.csv" tiba sebagai "Surat_Keluar_Masuk.csv").
        $peras = fn (string $t) => preg_replace('/[^\p{L}\p{N}]+/u', '', self::normalLembar($t)) ?? '';
        $n = $peras($lembar);
        if ($n === '') return null;

        foreach (self::semua() as $kunci => $m) {
            // Nama lembar Apps Script, atau judul modul — nama berkas CSV ekspor MERANTI
            // ("Target_Blok_(Rencana).csv") memakai judulnya.
            foreach (array_merge((array) $m['lembar'], [$m['judul']]) as $nama) {
                $nama = $peras($nama);
                if ($nama === $n || (mb_strlen($n) >= 10 && str_starts_with($nama, $n))) return $kunci;
            }
        }

        return null;
    }

    public static function normalLembar(string $lembar): string
    {
        $n = mb_strtolower(trim(str_replace('_', ' ', $lembar)));
        $n = preg_replace('/^meranti\s*-\s*/u', '', $n) ?? $n;

        return trim(preg_replace('/\s+/u', ' ', $n) ?? $n);
    }

    /**
     * Kolom untuk sebuah kepala lembar/CSV: kunci kolom ("luas_realisasi"),
     * alias lama ("stok_actual"), atau labelnya ("Luas Realisasi (Ha)") —
     * ekspor CSV MERANTI memakai label, bukan kunci.
     */
    public static function kolomUntukKepala(string $kunci, string $kepala): ?string
    {
        $m = self::modul($kunci);
        $h = trim($kepala);
        if (isset($m['alias'][$h])) return $m['alias'][$h];

        $normal = fn (string $t) => trim(preg_replace('/\s+/u', ' ', preg_replace('/\([^)]*\)/u', '', mb_strtolower($t)) ?? '') ?? '');
        foreach ($m['kolom'] as $k) {
            if ($k['k'] === $h || $normal($k['l']) === $normal($h)) return $k['k'];
        }

        return null;
    }

    /** Ringkas katalog untuk halaman: tanpa nama kelas model. */
    public static function untukHalaman(string $kunci): array
    {
        $m = self::modul($kunci);

        return [
            'kunci' => $kunci, 'judul' => $m['judul'], 'grup' => $m['grup'], 'ket' => $m['ket'], 'tambah' => $m['tambah'],
            'ikon' => $m['ikon'], 'slug' => $m['slug'], 'master' => $m['master'] ?? false, 'filter' => $m['filter'] ?? [],
            'jumlah' => $m['jumlah'] ?? [], 'urut' => $m['urut'] ?? [], 'hitung' => $m['hitung'] ?? null,
            'kolom' => array_map(fn ($k) => [
                'k' => $k['k'], 'l' => $k['l'], 't' => $k['t'], 'wajib' => $k['wajib'] ?? false, 'opsi' => $k['opsi'] ?? null,
                'des' => $k['des'] ?? null, 'satuan' => $k['satuan'] ?? null, 'lencana' => $k['lencana'] ?? false,
                'petunjuk' => $k['petunjuk'] ?? null, 'saran' => $k['saran'] ?? null, 'daftar' => $k['daftar'] ?? true,
                'panjang' => $k['panjang'] ?? null,
            ], $m['kolom']),
        ];
    }

    /** @return array<string, array<string, mixed>> */
    private static function susun(): array
    {
        $GOL = self::GOLONGAN;

        $modul = [
            /* ---------- NURSERY ---------- */
            'stok_bibit' => [
                'judul' => 'Stok Bibit', 'grup' => 'Nursery', 'slug' => 'stok-bibit', 'kelas' => M\StokBibit::class, 'lembar' => ['Stok Bibit'],
                'tambah' => 'Tambah stok bibit', 'ikon' => 'leaf', 'ket' => 'Persediaan bibit per jenis di persemaian.',
                'urut' => [['nama_bibit', 'asc']], 'jumlah' => ['stok'],
                'kolom' => [
                    ['k' => 'nama_bibit', 'l' => 'Nama Bibit', 't' => 'teks', 'wajib' => true, 'saran' => 'tanaman', 'panjang' => 150],
                    ['k' => 'stok', 'l' => 'Stok', 't' => 'angka', 'des' => 0, 'satuan' => 'batang'],
                    ['k' => 'golongan', 'l' => 'Golongan', 't' => 'pilih', 'opsi' => $GOL, 'lencana' => true],
                ],
            ],
            'bibit_masuk' => [
                'judul' => 'Bibit Masuk', 'grup' => 'Nursery', 'slug' => 'bibit-masuk', 'kelas' => M\BibitMasuk::class, 'lembar' => ['Bibit Masuk'],
                'tambah' => 'Tambah bibit masuk', 'ikon' => 'download', 'ket' => 'Bibit yang diterima persemaian — pembelian, kiriman, atau hasil semai.',
                'urut' => [['tanggal', 'desc']], 'jumlah' => ['jumlah'],
                'kolom' => [
                    ['k' => 'tanggal', 'l' => 'Tanggal', 't' => 'tanggal', 'wajib' => true],
                    ['k' => 'nama_bibit', 'l' => 'Nama Bibit', 't' => 'teks', 'wajib' => true, 'saran' => 'tanaman', 'panjang' => 150],
                    ['k' => 'jumlah', 'l' => 'Jumlah', 't' => 'angka', 'des' => 0, 'satuan' => 'batang'],
                    ['k' => 'sumber', 'l' => 'Sumber', 't' => 'teks', 'panjang' => 150],
                    ['k' => 'keterangan', 'l' => 'Keterangan', 't' => 'teks', 'panjang' => 500],
                ],
            ],
            'bibit_keluar' => [
                'judul' => 'Bibit Keluar', 'grup' => 'Nursery', 'slug' => 'bibit-keluar', 'kelas' => M\BibitKeluar::class, 'lembar' => ['Bibit Keluar'],
                'tambah' => 'Tambah bibit keluar', 'ikon' => 'truck', 'ket' => 'Bibit yang dikirim dari persemaian ke blok tanam.',
                'urut' => [['tanggal', 'desc']], 'jumlah' => ['jumlah'],
                'kolom' => [
                    ['k' => 'tanggal', 'l' => 'Tanggal', 't' => 'tanggal', 'wajib' => true],
                    ['k' => 'nama_bibit', 'l' => 'Nama Bibit', 't' => 'teks', 'wajib' => true, 'saran' => 'tanaman', 'panjang' => 150],
                    ['k' => 'jumlah', 'l' => 'Jumlah', 't' => 'angka', 'des' => 0, 'satuan' => 'batang'],
                    ['k' => 'tujuan', 'l' => 'Tujuan / Blok', 't' => 'teks', 'saran' => 'blok', 'panjang' => 150],
                    ['k' => 'keterangan', 'l' => 'Keterangan', 't' => 'teks', 'panjang' => 500],
                ],
            ],
            'bibit_kembali' => [
                'judul' => 'Bibit Kembali', 'grup' => 'Nursery', 'slug' => 'bibit-kembali', 'kelas' => M\BibitKembali::class, 'lembar' => ['Bibit Kembali'],
                'tambah' => 'Tambah bibit kembali', 'ikon' => 'recycle', 'ket' => 'Bibit yang dikembalikan ke persemaian dari lapangan.',
                'urut' => [['tanggal', 'desc']], 'jumlah' => ['jumlah'],
                'kolom' => [
                    ['k' => 'tanggal', 'l' => 'Tanggal', 't' => 'tanggal', 'wajib' => true],
                    ['k' => 'nama_bibit', 'l' => 'Nama Bibit', 't' => 'teks', 'wajib' => true, 'saran' => 'tanaman', 'panjang' => 150],
                    ['k' => 'jumlah', 'l' => 'Jumlah', 't' => 'angka', 'des' => 0, 'satuan' => 'batang'],
                    ['k' => 'keterangan', 'l' => 'Keterangan', 't' => 'teks', 'panjang' => 500],
                ],
            ],
            'bibit_mati' => [
                'judul' => 'Bibit Mati', 'grup' => 'Nursery', 'slug' => 'bibit-mati', 'kelas' => M\BibitMati::class, 'lembar' => ['Bibit Mati'],
                'tambah' => 'Tambah bibit mati', 'ikon' => 'circle-x', 'ket' => 'Bibit yang mati di persemaian beserta penyebabnya.',
                'urut' => [['tanggal', 'desc']], 'jumlah' => ['jumlah'],
                'kolom' => [
                    ['k' => 'tanggal', 'l' => 'Tanggal', 't' => 'tanggal', 'wajib' => true],
                    ['k' => 'nama_bibit', 'l' => 'Nama Bibit', 't' => 'teks', 'wajib' => true, 'saran' => 'tanaman', 'panjang' => 150],
                    ['k' => 'jumlah', 'l' => 'Jumlah', 't' => 'angka', 'des' => 0, 'satuan' => 'batang'],
                    ['k' => 'penyebab', 'l' => 'Penyebab', 't' => 'teks', 'panjang' => 190],
                ],
            ],
            'bibit_rusak' => [
                'judul' => 'Bibit Rusak', 'grup' => 'Nursery', 'slug' => 'bibit-rusak', 'kelas' => M\BibitRusak::class, 'lembar' => ['Bibit Rusak'],
                'tambah' => 'Tambah bibit rusak', 'ikon' => 'triangle-alert', 'ket' => 'Bibit yang rusak dan tidak layak tanam.',
                'urut' => [['tanggal', 'desc']], 'jumlah' => ['jumlah'],
                'kolom' => [
                    ['k' => 'tanggal', 'l' => 'Tanggal', 't' => 'tanggal', 'wajib' => true],
                    ['k' => 'nama_bibit', 'l' => 'Nama Bibit', 't' => 'teks', 'wajib' => true, 'saran' => 'tanaman', 'panjang' => 150],
                    ['k' => 'jumlah', 'l' => 'Jumlah', 't' => 'angka', 'des' => 0, 'satuan' => 'batang'],
                    ['k' => 'penyebab', 'l' => 'Penyebab', 't' => 'teks', 'panjang' => 190],
                ],
            ],
            'master_tanaman' => [
                'judul' => 'Master Tanaman', 'grup' => 'Nursery', 'slug' => 'master-tanaman', 'kelas' => M\Tanaman::class, 'lembar' => ['Master Tanaman'],
                'tambah' => 'Tambah tanaman', 'ikon' => 'trees', 'ket' => 'Daftar jenis tanaman — nama lokal, nama latin, dan golongannya.', 'master' => true,
                'urut' => [['golongan', 'asc'], ['nama_tanaman', 'asc']],
                'kolom' => [
                    ['k' => 'nama_tanaman', 'l' => 'Nama Tanaman', 't' => 'teks', 'wajib' => true, 'panjang' => 150],
                    ['k' => 'nama_latin', 'l' => 'Nama Latin', 't' => 'teks', 'panjang' => 150],
                    ['k' => 'golongan', 'l' => 'Golongan', 't' => 'pilih', 'opsi' => $GOL, 'lencana' => true],
                ],
            ],

            /* ---------- MATERIAL ---------- */
            'stok_material' => [
                'judul' => 'Stok Material', 'grup' => 'Material Stok', 'slug' => 'stok-material', 'kelas' => M\StokMaterial::class, 'lembar' => ['Stok Material'],
                'tambah' => 'Tambah stok material', 'ikon' => 'package', 'ket' => 'Persediaan pupuk, benih, polybag, dan perlengkapan persemaian.',
                'urut' => [['nama_material', 'asc']],
                'kolom' => [
                    ['k' => 'nama_material', 'l' => 'Nama Material', 't' => 'teks', 'wajib' => true, 'panjang' => 150],
                    ['k' => 'material_stok', 'l' => 'Material Stok', 't' => 'angka', 'des' => 2],
                    ['k' => 'nama_satuan', 'l' => 'Nama Satuan', 't' => 'pilih', 'opsi' => self::SATUAN],
                ],
            ],
            'material_masuk' => [
                'judul' => 'Material Masuk', 'grup' => 'Material Stok', 'slug' => 'material-masuk', 'kelas' => M\MaterialMasuk::class, 'lembar' => ['Material Masuk'],
                'tambah' => 'Tambah material masuk', 'ikon' => 'download', 'ket' => 'Material yang diterima gudang persemaian.',
                'urut' => [['tanggal', 'desc']],
                'kolom' => [
                    ['k' => 'tanggal', 'l' => 'Tanggal', 't' => 'tanggal', 'wajib' => true],
                    ['k' => 'nama_material', 'l' => 'Nama Material', 't' => 'teks', 'wajib' => true, 'saran' => 'material', 'panjang' => 150],
                    ['k' => 'jumlah', 'l' => 'Jumlah', 't' => 'angka', 'des' => 2],
                    ['k' => 'satuan', 'l' => 'Satuan', 't' => 'teks', 'saran' => 'satuan', 'panjang' => 30],
                    ['k' => 'sumber', 'l' => 'Sumber', 't' => 'teks', 'panjang' => 150],
                ],
            ],
            'material_keluar' => [
                'judul' => 'Material Keluar', 'grup' => 'Material Stok', 'slug' => 'material-keluar', 'kelas' => M\MaterialKeluar::class, 'lembar' => ['Material Keluar'],
                'tambah' => 'Tambah material keluar', 'ikon' => 'truck', 'ket' => 'Material yang dipakai atau dikirim ke lapangan.',
                'urut' => [['tanggal', 'desc']],
                'kolom' => [
                    ['k' => 'tanggal', 'l' => 'Tanggal', 't' => 'tanggal', 'wajib' => true],
                    ['k' => 'nama_material', 'l' => 'Nama Material', 't' => 'teks', 'wajib' => true, 'saran' => 'material', 'panjang' => 150],
                    ['k' => 'jumlah', 'l' => 'Jumlah', 't' => 'angka', 'des' => 2],
                    ['k' => 'satuan', 'l' => 'Satuan', 't' => 'teks', 'saran' => 'satuan', 'panjang' => 30],
                    ['k' => 'tujuan', 'l' => 'Tujuan', 't' => 'teks', 'saran' => 'blok', 'panjang' => 150],
                ],
            ],

            /* ---------- REHABILITASI DAS ---------- */
            'progres_mingguan' => [
                'judul' => 'Progres Mingguan', 'grup' => 'Rehabilitasi DAS', 'slug' => 'progres-mingguan', 'kelas' => M\Progres::class, 'lembar' => ['Progres Mingguan'],
                'tambah' => 'Tambah progres mingguan', 'ikon' => 'sprout', 'ket' => 'Realisasi pekerjaan rehabilitasi DAS per tahun → bulan → minggu.',
                'filter' => ['tahun', 'bulan'], 'urut' => [['tahun', 'desc'], ['bulan', 'desc'], ['minggu', 'desc']], 'jumlah' => ['luas_realisasi'],
                'kolom' => [
                    ['k' => 'tahun', 'l' => 'Tahun', 't' => 'angka', 'des' => 0, 'wajib' => true, 'min' => 2000, 'maks' => 2100, 'polos' => true],
                    ['k' => 'bulan', 'l' => 'Bulan', 't' => 'pilih', 'wajib' => true, 'opsi' => self::BULAN],
                    ['k' => 'minggu', 'l' => 'Minggu', 't' => 'pilih', 'wajib' => true, 'opsi' => self::MINGGU, 'lencana' => true],
                    ['k' => 'tanggal', 'l' => 'Tanggal', 't' => 'tanggal'],
                    ['k' => 'pelaksana', 'l' => 'Pelaksana', 't' => 'teks', 'saran' => 'pelaksana', 'panjang' => 150],
                    ['k' => 'pekerjaan', 'l' => 'Pekerjaan', 't' => 'pilih', 'opsi' => self::PEKERJAAN, 'lencana' => true, 'petunjuk' => self::PETUNJUK_P],
                    ['k' => 'nama_blok', 'l' => 'Nama Blok', 't' => 'teks', 'wajib' => true, 'saran' => 'blok', 'panjang' => 100],
                    ['k' => 'luas_realisasi', 'l' => 'Luas Realisasi', 't' => 'angka', 'des' => 2, 'satuan' => 'Ha'],
                    ['k' => 'keterangan', 'l' => 'Keterangan', 't' => 'teks', 'panjang' => 500],
                ],
            ],
            'target_blok' => [
                'judul' => 'Target Blok (Rencana)', 'grup' => 'Rehabilitasi DAS', 'slug' => 'target-blok', 'kelas' => M\TargetBlok::class, 'lembar' => ['Target Blok DAS', 'Target Blok'],
                'tambah' => 'Tambah target blok', 'ikon' => 'target', 'ket' => 'Rencana luas P0/P1/P2 per blok — dasar persentase Aktual Pencapaian.',
                'urut' => [['tahun', 'desc'], ['nama_blok', 'asc'], ['pekerjaan', 'asc']], 'jumlah' => ['luas_target'],
                'kolom' => [
                    ['k' => 'nama_blok', 'l' => 'Nama Blok', 't' => 'teks', 'wajib' => true, 'saran' => 'blok', 'panjang' => 100],
                    ['k' => 'pekerjaan', 'l' => 'Pekerjaan', 't' => 'pilih', 'wajib' => true, 'opsi' => self::PEKERJAAN, 'lencana' => true, 'petunjuk' => self::PETUNJUK_P],
                    ['k' => 'luas_target', 'l' => 'Luas Target', 't' => 'angka', 'wajib' => true, 'des' => 2, 'satuan' => 'Ha'],
                    ['k' => 'tahun', 'l' => 'Tahun', 't' => 'angka', 'des' => 0, 'min' => 2000, 'maks' => 2100, 'polos' => true],
                    ['k' => 'keterangan', 'l' => 'Keterangan', 't' => 'teks', 'panjang' => 500],
                ],
            ],
            'actual_capaian' => [
                'judul' => 'Arsip File Capaian', 'grup' => 'Rehabilitasi DAS', 'slug' => 'arsip-capaian', 'kelas' => M\ArsipCapaian::class, 'lembar' => ['Actual Data Capaian', 'Arsip File Capaian'],
                'tambah' => 'Tambah arsip capaian', 'ikon' => 'file-check-2', 'ket' => 'Tautan berkas capaian per project, pelaksana, dan minggu.',
                'urut' => [['tahun', 'desc'], ['id', 'desc']],
                'kolom' => [
                    ['k' => 'nama_project', 'l' => 'Nama Project', 't' => 'teks', 'wajib' => true, 'saran' => 'project', 'panjang' => 150],
                    ['k' => 'kategori_pelaksana', 'l' => 'Kategori Pelaksana', 't' => 'pilih', 'opsi' => self::KATEGORI_PELAKSANA, 'lencana' => true],
                    ['k' => 'pelaksana', 'l' => 'Pelaksana', 't' => 'teks', 'saran' => 'pelaksana', 'panjang' => 150],
                    ['k' => 'tahun', 'l' => 'Tahun', 't' => 'angka', 'des' => 0, 'min' => 2000, 'maks' => 2100, 'polos' => true],
                    ['k' => 'bulan', 'l' => 'Bulan', 't' => 'pilih', 'opsi' => self::BULAN],
                    ['k' => 'week', 'l' => 'Week', 't' => 'teks', 'panjang' => 20],
                    ['k' => 'file', 'l' => 'File', 't' => 'tautan'],
                    ['k' => 'penginput', 'l' => 'Penginput', 't' => 'teks', 'panjang' => 100],
                ],
            ],
            'aktual_penanaman' => [
                'judul' => 'Aktual Penanaman', 'grup' => 'Rehabilitasi DAS', 'slug' => 'aktual-penanaman', 'kelas' => M\Penanaman::class, 'lembar' => ['Actual Penanaman', 'Aktual Penanaman'],
                'tambah' => 'Tambah aktual penanaman', 'ikon' => 'shovel', 'ket' => 'Jumlah bibit yang ditanam per jenis, kegiatan, pelaksana, dan blok.',
                'urut' => [['tanggal', 'desc']], 'jumlah' => ['jumlah'],
                'alias' => ['stok_actual' => 'jumlah', 'nama_pelaksana' => 'pelaksana'],
                'kolom' => [
                    ['k' => 'tanggal', 'l' => 'Tanggal', 't' => 'tanggal', 'wajib' => true],
                    ['k' => 'nama_bibit', 'l' => 'Nama Bibit', 't' => 'teks', 'wajib' => true, 'saran' => 'tanaman', 'panjang' => 150],
                    ['k' => 'golongan', 'l' => 'Golongan', 't' => 'pilih', 'opsi' => $GOL, 'lencana' => true],
                    ['k' => 'jumlah', 'l' => 'Jumlah Ditanam', 't' => 'angka', 'des' => 0, 'satuan' => 'batang'],
                    ['k' => 'nama_project', 'l' => 'Project', 't' => 'teks', 'saran' => 'project', 'panjang' => 150],
                    ['k' => 'nama_kegiatan', 'l' => 'Kegiatan', 't' => 'teks', 'panjang' => 190],
                    ['k' => 'pelaksana', 'l' => 'Pelaksana', 't' => 'teks', 'saran' => 'pelaksana', 'panjang' => 150],
                    ['k' => 'nama_blok', 'l' => 'Nama Blok', 't' => 'teks', 'saran' => 'blok', 'panjang' => 100],
                    ['k' => 'penginput', 'l' => 'Penginput', 't' => 'teks', 'panjang' => 100],
                ],
            ],

            /* ---------- REKLAMASI (KEPATUHAN) ---------- */
            'rencana_reklamasi' => [
                'judul' => 'Rencana Reklamasi', 'grup' => 'Reklamasi', 'slug' => 'rencana-reklamasi', 'kelas' => M\RencanaReklamasi::class, 'lembar' => ['Rencana Reklamasi'],
                'tambah' => 'Tambah rencana', 'ikon' => 'calendar-check', 'ket' => 'Luas bukaan, luas reklamasi, rencana bibit, dan biaya per tahun.',
                'urut' => [['tahun', 'asc']], 'jumlah' => ['luas_bukaan_ha', 'luas_reklamasi_ha', 'jumlah_bibit_rencana', 'biaya_rencana'],
                'kolom' => [
                    ['k' => 'tahun', 'l' => 'Tahun', 't' => 'angka', 'des' => 0, 'wajib' => true, 'min' => 2000, 'maks' => 2100, 'polos' => true],
                    ['k' => 'luas_bukaan_ha', 'l' => 'Luas Bukaan', 't' => 'angka', 'des' => 2, 'satuan' => 'Ha'],
                    ['k' => 'luas_reklamasi_ha', 'l' => 'Luas Reklamasi', 't' => 'angka', 'des' => 2, 'satuan' => 'Ha'],
                    ['k' => 'jumlah_bibit_rencana', 'l' => 'Rencana Bibit', 't' => 'angka', 'des' => 0, 'satuan' => 'btg'],
                    ['k' => 'biaya_rencana', 'l' => 'Biaya Rencana', 't' => 'angka', 'des' => 0, 'satuan' => 'Rp'],
                    ['k' => 'keterangan', 'l' => 'Keterangan', 't' => 'teks', 'panjang' => 500],
                ],
            ],
            'penilaian_keberhasilan' => [
                'judul' => 'Penilaian Keberhasilan', 'grup' => 'Reklamasi', 'slug' => 'penilaian-keberhasilan', 'kelas' => M\Penilaian::class, 'lembar' => ['Penilaian Keberhasilan'],
                'tambah' => 'Tambah penilaian', 'ikon' => 'badge-check', 'ket' => 'Matrik 17: penatagunaan lahan 60%, revegetasi 20%, penyelesaian akhir 20%.',
                'hitung' => 'matrik17', 'urut' => [['tahun', 'desc'], ['blok', 'asc']],
                'kolom' => [
                    ['k' => 'blok', 'l' => 'Blok / Area', 't' => 'teks', 'wajib' => true, 'saran' => 'blok', 'panjang' => 100],
                    ['k' => 'tahun', 'l' => 'Tahun', 't' => 'angka', 'des' => 0, 'min' => 2000, 'maks' => 2100, 'polos' => true],
                    ['k' => 'nilai_penatagunaan', 'l' => 'Penatagunaan Lahan', 't' => 'angka', 'des' => 2, 'maks' => 100, 'petunjuk' => 'Nilai 0–100 · bobot 60%'],
                    ['k' => 'nilai_revegetasi', 'l' => 'Revegetasi', 't' => 'angka', 'des' => 2, 'maks' => 100, 'petunjuk' => 'Nilai 0–100 · bobot 20%'],
                    ['k' => 'nilai_penyelesaian', 'l' => 'Penyelesaian Akhir', 't' => 'angka', 'des' => 2, 'maks' => 100, 'petunjuk' => 'Nilai 0–100 · bobot 20%'],
                    ['k' => 'skor_total', 'l' => 'Skor Total', 't' => 'hitung', 'des' => 2],
                    ['k' => 'kategori', 'l' => 'Kategori', 't' => 'hitung', 'lencana' => true],
                ],
            ],
            'jaminan_reklamasi' => [
                'judul' => 'Jaminan Reklamasi', 'grup' => 'Reklamasi', 'slug' => 'jaminan-reklamasi', 'kelas' => M\Jaminan::class, 'lembar' => ['Jaminan Reklamasi'],
                'tambah' => 'Tambah jaminan', 'ikon' => 'landmark', 'ket' => 'Penempatan dan pencairan jaminan reklamasi serta pascatambang.',
                'urut' => [['tanggal_penempatan', 'desc']], 'jumlah' => ['nilai'],
                'kolom' => [
                    ['k' => 'periode', 'l' => 'Periode', 't' => 'teks', 'wajib' => true, 'panjang' => 60],
                    ['k' => 'jenis', 'l' => 'Jenis', 't' => 'pilih', 'opsi' => ['Jaminan Reklamasi', 'Jaminan Pascatambang']],
                    ['k' => 'nilai', 'l' => 'Nilai', 't' => 'angka', 'des' => 0, 'satuan' => 'Rp'],
                    ['k' => 'tanggal_penempatan', 'l' => 'Tgl Penempatan', 't' => 'tanggal'],
                    ['k' => 'status', 'l' => 'Status', 't' => 'pilih', 'opsi' => ['Ditempatkan', 'Sebagian Dicairkan', 'Dicairkan'], 'lencana' => true],
                ],
            ],

            /* ---------- ADMINISTRASI ---------- */
            'surat' => [
                'judul' => 'Surat Keluar/Masuk', 'grup' => 'Administrasi', 'slug' => 'surat', 'kelas' => M\Surat::class, 'lembar' => ['Surat'],
                'tambah' => 'Tambah surat', 'ikon' => 'file-text', 'ket' => 'Register surat masuk dan keluar terkait reklamasi dan persemaian.',
                'urut' => [['tanggal', 'desc']],
                'kolom' => [
                    ['k' => 'nomor_surat', 'l' => 'Nomor Surat', 't' => 'teks', 'wajib' => true, 'panjang' => 120],
                    ['k' => 'jenis', 'l' => 'Jenis', 't' => 'pilih', 'opsi' => ['Masuk', 'Keluar'], 'lencana' => true],
                    ['k' => 'tanggal', 'l' => 'Tanggal', 't' => 'tanggal'],
                    ['k' => 'perihal', 'l' => 'Perihal', 't' => 'teks', 'panjang' => 255],
                    ['k' => 'dari_kepada', 'l' => 'Dari / Kepada', 't' => 'teks', 'panjang' => 190],
                ],
            ],
            'pengajuan_dana' => [
                'judul' => 'Pengajuan Dana & Control Budget', 'grup' => 'Administrasi', 'slug' => 'pengajuan-dana', 'kelas' => M\PengajuanDana::class, 'lembar' => ['Pengajuan Dana'],
                'tambah' => 'Tambah pengajuan', 'ikon' => 'banknote', 'ket' => 'Pengajuan dana per project dan periode beserta statusnya.',
                'urut' => [['id', 'desc']], 'jumlah' => ['nilai'],
                'kolom' => [
                    ['k' => 'nomor', 'l' => 'Nomor Pengajuan', 't' => 'teks', 'wajib' => true, 'panjang' => 120],
                    ['k' => 'project', 'l' => 'Project', 't' => 'teks', 'saran' => 'project', 'panjang' => 150],
                    ['k' => 'periode', 'l' => 'Periode', 't' => 'teks', 'saran' => 'periode', 'panjang' => 100],
                    ['k' => 'nilai', 'l' => 'Nilai', 't' => 'angka', 'des' => 0, 'satuan' => 'Rp'],
                    ['k' => 'status', 'l' => 'Status', 't' => 'pilih', 'opsi' => ['Diajukan', 'Disetujui', 'Ditolak', 'Dibayar'], 'lencana' => true],
                ],
            ],
            'berita_acara' => [
                'judul' => 'Berita Acara', 'grup' => 'Administrasi', 'slug' => 'berita-acara', 'kelas' => M\BeritaAcara::class, 'lembar' => ['Berita Acara'],
                'tambah' => 'Tambah berita acara', 'ikon' => 'clipboard-check', 'ket' => 'Berita acara serah terima, pemeriksaan, dan kegiatan lapangan.',
                'urut' => [['tanggal', 'desc']],
                'kolom' => [
                    ['k' => 'nomor', 'l' => 'Nomor BA', 't' => 'teks', 'wajib' => true, 'panjang' => 120],
                    ['k' => 'judul', 'l' => 'Judul', 't' => 'teks', 'panjang' => 255],
                    ['k' => 'tanggal', 'l' => 'Tanggal', 't' => 'tanggal'],
                    ['k' => 'terkait', 'l' => 'Terkait', 't' => 'teks', 'panjang' => 190],
                ],
            ],
            'periode_pengajuan' => [
                'judul' => 'Periode Pengajuan', 'grup' => 'Administrasi', 'slug' => 'periode-pengajuan', 'kelas' => M\Periode::class, 'lembar' => ['Periode Pengajuan'],
                'tambah' => 'Tambah periode', 'ikon' => 'calendar-clock', 'ket' => 'Periode pengajuan dana yang sedang dibuka atau sudah ditutup.',
                'urut' => [['tanggal_mulai', 'desc']],
                'kolom' => [
                    ['k' => 'nama_periode', 'l' => 'Nama Periode', 't' => 'teks', 'wajib' => true, 'panjang' => 150],
                    ['k' => 'tanggal_mulai', 'l' => 'Tanggal Mulai', 't' => 'tanggal'],
                    ['k' => 'tanggal_selesai', 'l' => 'Tanggal Selesai', 't' => 'tanggal'],
                    ['k' => 'status', 'l' => 'Status', 't' => 'pilih', 'opsi' => ['Aktif', 'Tutup'], 'lencana' => true],
                ],
            ],

            /* ---------- DATA MASTER ---------- */
            'master_project' => [
                'judul' => 'Master Project', 'grup' => 'Data Master', 'slug' => 'master-project', 'kelas' => M\Project::class, 'lembar' => ['Master Project'],
                'tambah' => 'Tambah project', 'ikon' => 'layers', 'ket' => 'Jenis project: revegetasi, pemeliharaan, pengayaan, dan lainnya.', 'master' => true,
                'urut' => [['kode_project', 'asc']],
                'kolom' => [
                    ['k' => 'kode_project', 'l' => 'Kode Project', 't' => 'teks', 'panjang' => 40],
                    ['k' => 'nama_project', 'l' => 'Nama Project', 't' => 'teks', 'wajib' => true, 'panjang' => 150],
                    ['k' => 'keterangan', 'l' => 'Keterangan', 't' => 'teks', 'panjang' => 500],
                ],
            ],
            'master_pelaksana' => [
                'judul' => 'Master Pelaksana', 'grup' => 'Data Master', 'slug' => 'master-pelaksana', 'kelas' => M\Pelaksana::class, 'lembar' => ['Master Pelaksana'],
                'tambah' => 'Tambah pelaksana', 'ikon' => 'users', 'ket' => 'Vendor dan tim in-house yang mengerjakan penanaman dan pemeliharaan.', 'master' => true,
                'urut' => [['kategori', 'asc'], ['nama_pelaksana', 'asc']],
                'kolom' => [
                    ['k' => 'nama_pelaksana', 'l' => 'Nama Pelaksana', 't' => 'teks', 'wajib' => true, 'panjang' => 150],
                    ['k' => 'kategori', 'l' => 'Kategori', 't' => 'pilih', 'opsi' => self::KATEGORI_PELAKSANA, 'lencana' => true],
                ],
            ],
            'project_vendor' => [
                'judul' => 'Project Vendor', 'grup' => 'Data Master', 'slug' => 'project-vendor', 'kelas' => M\ProjectVendor::class, 'lembar' => ['Project Vendor'],
                'tambah' => 'Tambah project vendor', 'ikon' => 'workflow', 'ket' => 'Pasangan vendor dan project yang dikerjakannya.', 'master' => true,
                'urut' => [['kode_project_vendor', 'asc']],
                'kolom' => [
                    ['k' => 'kode_project_vendor', 'l' => 'Kode Project Vendor', 't' => 'teks', 'wajib' => true, 'panjang' => 40],
                    ['k' => 'nama_vendor', 'l' => 'Nama Vendor', 't' => 'teks', 'saran' => 'pelaksana', 'panjang' => 150],
                    ['k' => 'nama_project', 'l' => 'Nama Project', 't' => 'teks', 'saran' => 'project', 'panjang' => 150],
                ],
            ],
            'peta_spasial' => [
                'judul' => 'Peta Spasial', 'grup' => 'Data Master', 'slug' => 'peta-spasial', 'kelas' => M\Peta::class, 'lembar' => ['Peta Spasial'],
                'tambah' => 'Tambah fitur manual', 'ikon' => 'map', 'ket' => 'Fitur peta dari KML/KMZ/GeoJSON — blok, petak, batas area, titik tanam.',
                'urut' => [['id', 'asc']], 'jumlah' => ['luas_ha'],
                'kolom' => [
                    ['k' => 'nama_fitur', 'l' => 'Nama Fitur / Blok', 't' => 'teks', 'wajib' => true, 'saran' => 'blok', 'panjang' => 150],
                    ['k' => 'kategori', 'l' => 'Kategori', 't' => 'pilih', 'opsi' => self::KATEGORI_PETA, 'lencana' => true],
                    ['k' => 'petak', 'l' => 'Petak', 't' => 'teks', 'panjang' => 60],
                    ['k' => 'week', 'l' => 'Week', 't' => 'teks', 'panjang' => 30],
                    ['k' => 'month', 'l' => 'Month', 't' => 'teks', 'panjang' => 20],
                    ['k' => 'year', 'l' => 'Year', 't' => 'angka', 'des' => 0, 'min' => 1900, 'maks' => 2100, 'polos' => true],
                    ['k' => 'luas_ha', 'l' => 'Luas', 't' => 'angka', 'des' => 4, 'satuan' => 'Ha'],
                    ['k' => 'geojson', 'l' => 'GeoJSON', 't' => 'panjang', 'daftar' => false, 'panjang' => 2_000_000, 'petunjuk' => 'Diisi otomatis saat unggah KML/KMZ/GeoJSON di halaman Peta'],
                    ['k' => 'sumber_file', 'l' => 'Sumber File', 't' => 'teks', 'panjang' => 190],
                ],
            ],
        ];

        return $modul;
    }
}
