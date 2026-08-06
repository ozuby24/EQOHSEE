<?php

namespace App\Support;

/**
 * Aturan Hazard Report — replika dari aplikasi asli (Hazrep/Index.html).
 * Target KPI ditentukan dari GOLONGAN JABATAN, bukan dari peran aplikasi.
 */
class Hazard
{
    public const RISIKO   = ['Rendah', 'Sedang', 'Tinggi'];
    public const STATUS   = ['Open', 'In Progress', 'Closed'];
    public const HIRARKI  = ['Eliminasi', 'Substitusi', 'Rekayasa', 'Administratif', 'APD'];

    /** Kategori hazard — sesuai daftar pada aplikasi asli. */
    public const KATEGORI = [
        'Unsafe Action',
        'Unsafe Condition',
        'Unsafe Action & Unsafe Condition',
        'Near Miss',
        'Bahaya Lingkungan',
    ];

    /** Jabatan baku — menentukan golongan & target KPI, karena itu dipilih, bukan diketik. */
    public const JABATAN = [
        'General Manager', 'Manager', 'Penanggung Jawab Operasional',
        'Superintendent', 'Supervisor', 'Officer', 'Non Staff', 'Lainnya',
    ];

    /** Departemen baku (bisa diketik lain lewat datalist). */
    public const DEPARTEMEN = [
        'OHSE', 'Operation', 'Engineering', 'Geolist and Quality Control',
        'MIK - CHF', 'BPK - CHRM', 'HRGA', 'ICT',
    ];

    /** Lokasi baku — sesuai daftar pada aplikasi asli (20 lokasi). */
    public const LOKASI = [
        'Area PIT - Front Loading',
        'Area PIT - Disposal',
        'Area PIT - Jalan Tambang',
        'Banksoil',
        'Reklamasi',
        'CPP 62',
        'CPP 64',
        'Stockpile',
        'Port',
        'ROM',
        'Workshop PT PST',
        'Workshop PT DMP',
        'Coal Hauling Road',
        'Fuel Station/Storage',
        'Office 22',
        'Office 63',
        'Gudang',
        'Mess',
        'Kantin',
        'Lainnya',
    ];

    /** Bentuk unsafe action — daftar centang pada aplikasi asli (15 butir). */
    public const UNSAFE_ACTION = [
        'Mengoperasikan Perlatan Tanpa Izin',
        'Gagal Untuk Memperingatkan',
        'Gagal Untuk Mengamankan',
        'Mengoperasikan Dengan Kecepatan Tidak Layak',
        'Membuat Peralatan Pengaman Tidak Berfungsi',
        'Menggunakan Peralatan Yang Rusak',
        'Tidak Menggunakan APD Dengan Benar',
        'Muatan Yang Tidak Layak Berfungsi',
        'Penempatan Yang Tidak Layak',
        'Pengangkatan Yang Tidak Tepat',
        'Posisi Kerja Tidak Tepat',
        'Perawatan/Perbaikan Perlatan Yang Sedang Beroperasi',
        'Bercada Atau Bersendagurau',
        'Dibawah Pengaruh Alkhol atau Obat-obatan Terlarang',
        'Tidak Mengikuti Prosedur',
    ];

    /** Bentuk unsafe condition — daftar centang pada aplikasi asli (20 butir). */
    public const UNSAFE_CONDITION = [
        'Pengaman/Pelindung Tidak Layak',
        'Kurang Atau Tidak Tersedia Peralatan Pengaman',
        'Peralatan Atau Material Rusak',
        'Kepadatan atau Keterbatasan Gerak',
        'Sistem Pemberitahuan/Peringatan Tidak Layak',
        'Bahaya Kebakaran Atau Ledakan',
        'Kebersihan Atau Kerapian Tidak Layak',
        'Paparan Debu',
        'Paparan Radiasi',
        'Temperatur Ekstrim',
        'Penerangan Berlebihan atau Kurang',
        'Kurang Ventilasi',
        'Kondisi Lingkungan Berbahaya',
        'Kondisi Jalan Tambang/Jalan Hauling Kurang Dari 3,5 x Lebar Kendaraan Terbesar',
        'Tinggi Tanggul Kurang Dari 3/4 Tinggi Ban Kendaraan Terbesar',
        'Grade Jalan Lebih Dari 8%',
        'Grade Front Loading Lebih Dari 2%',
        'Luasan Area Front Loading Tidak Standar',
        'Tinggi Slope Lebih Dari 10m dan Bench Kurang dari 5m',
        'Kondisi Jalan Tambang Undulating /Tidak Layak/Tergenang',
    ];

    public const JENIS_INSPEKSI = ['Harian', 'Mingguan', 'Bulanan', 'Khusus'];
    public const KONDISI        = ['Sesuai', 'Tidak Sesuai', 'N/A'];

    public const WARNA_RISIKO = ['Rendah' => '#84cc16', 'Sedang' => '#f59e0b', 'Tinggi' => '#ef4444'];
    public const WARNA_STATUS = ['Open' => '#ef4444', 'In Progress' => '#f59e0b', 'Closed' => '#84cc16'];

    /** Golongan jabatan untuk pengelompokan KPI. */
    public static function golongan(?string $jabatan): string
    {
        $j = mb_strtolower((string) $jabatan);
        return match (true) {
            str_contains($j, 'general manager')      => 'General Manager',
            str_contains($j, 'manager')              => 'Manager',
            str_contains($j, 'penanggung jawab')     => 'Manager',      // setara Manager
            str_contains($j, 'superintendent')       => 'Superintendent',
            str_contains($j, 'supervisor')           => 'Supervisor',
            str_contains($j, 'officer')              => 'Officer',
            str_contains($j, 'non staff'),
            str_contains($j, 'nonstaff')             => 'Non Staff',
            default                                  => $jabatan ?: 'Lainnya',
        };
    }

    /** Target laporan per bulan menurut golongan jabatan. */
    public static function target(?string $jabatan): int
    {
        $j = mb_strtolower((string) $jabatan);
        return match (true) {
            str_contains($j, 'general manager')  => 1,
            str_contains($j, 'manager')          => 1,
            str_contains($j, 'penanggung jawab') => 1,
            str_contains($j, 'superintendent')   => 3,
            default                              => 4,   // supervisor, officer, non staff, lainnya
        };
    }
}
