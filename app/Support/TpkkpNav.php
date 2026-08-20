<?php

namespace App\Support;

use App\Models\TpkkpAssessment;

/**
 * Navigasi dalam-halaman modul PTPKKP (deretan chip di atas isi).
 *
 * Daftarnya dulu tertulis di dalam resources/views/tpkkp/_picker.blade.php.
 * Selama seluruh halaman PTPKKP berupa Blade, itu memadai. Begitu dua
 * halaman pindah ke Vue, larik di dalam view tidak dapat dijangkau tanpa
 * merender view-nya — dan yang terjadi persis seperti yang bisa diduga:
 * kedua halaman Vue itu terkirim TANPA navigasi sama sekali, sehingga
 * satu-satunya jalan keluar dari halaman Penilaian adalah tombol back
 * peramban.
 *
 * Ikon disimpan sebagai atribut `d` di sini juga, bukan hanya sebagai
 * <symbol> di dalam Blade, supaya sisi Vue menggambar ikon yang sama
 * tanpa menyalin ulang gambarnya.
 */
final class TpkkpNav
{
    /** [nama rute, label, kunci ikon] */
    public const TABS = [
        ['tpkkp.index',     'Beranda',        'home'],
        ['tpkkp.assess',    'Penilaian',      'edit'],
        ['tpkkp.matriks',   'Matriks',        'grid'],
        ['tpkkp.summary',   'Summary',        'target'],
        ['tpkkp.hasil',     'Hasil',          'award'],
        ['tpkkp.rekap',     'Rekapitulasi',   'list'],
        ['tpkkp.visual',    'Visualisasi',    'chart'],
        ['tpkkp.program',   'Program',        'spark'],
        ['tpkkp.jadwal',    'Jadwal',         'calendar'],
        ['tpkkp.sampling',  'Slovin',         'calc'],
        ['tpkkp.sampel',    'Rencana Sampel', 'users'],
        ['tpkkp.metode',    'Metode',         'layers'],
        ['tpkkp.rubrik',    'Rubrik',         'book'],
        ['tpkkp.roster',    'Mitra & Akses',  'building'],
        ['tpkkp.kuesioner', 'Kuesioner',      'poll'],
        ['tpkkp.pengujian', 'Pengujian',      'quiz'],
        ['tpkkp.data',      'Data',           'database'],
        ['tpkkp.profile',   'Profil',         'user'],
        ['tpkkp.tentang',   'Instrumen',      'info'],
    ];

    /** Gambar ikon, digambar penuh (fill) pada kanvas 24×24. */
    public const IKON = [
        'home'     => 'M12 3 3 10v11h6v-6h6v6h6V10Z',
        'edit'     => 'M4 17.2V20h2.8L17 9.8 14.2 7Zm14.7-9.9a1 1 0 0 0 0-1.4l-1.6-1.6a1 1 0 0 0-1.4 0L14.3 5.7 17.1 8.5Z',
        'grid'     => 'M3 3h8v6H3Zm10 0h8v6h-8ZM3 11h8v10H3Zm10 0h8v10h-8Z',
        'target'   => 'M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8Zm0-13a5 5 0 1 0 5 5 5 5 0 0 0-5-5Zm0 8a3 3 0 1 1 3-3 3 3 0 0 1-3 3Z',
        'award'    => 'M12 2a6 6 0 1 0 6 6 6 6 0 0 0-6-6Zm0 10a4 4 0 1 1 4-4 4 4 0 0 1-4 4Zm-4 3-2 7 6-3 6 3-2-7a8 8 0 0 1-8 0Z',
        'list'     => 'M4 5h16v2H4Zm0 6h16v2H4Zm0 6h16v2H4Z',
        'chart'    => 'M4 20h16v1.5H4ZM6 11h3v8H6Zm5-6h3v14h-3Zm5 4h3v10h-3Z',
        'spark'    => 'm13 2-9 12h6l-2 8 9-12h-6Z',
        'calendar' => 'M7 2v2H5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2V2h-2v2H9V2Zm12 17H5V10h14Z',
        'calc'     => 'M6 2h12a2 2 0 0 1 2 2v16a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm1 3v3h10V5Zm0 6v2h3v-2Zm5 0v2h3v-2Zm-5 5v2h3v-2Zm5 0v2h3v-2Z',
        'users'    => 'M9 11a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-3 0-8 1.4-8 4.3V21h16v-3.7C17 14.4 12 13 9 13Zm9-2a3.5 3.5 0 1 0-3.5-3.5A3.5 3.5 0 0 0 18 11Zm.5 2c-.6 0-1.3.1-1.9.2A5.3 5.3 0 0 1 19 17.3V21h5v-3.7c0-2.6-3.4-4.3-5.5-4.3Z',
        'layers'   => 'm12 3 9 5-9 5-9-5Zm0 12.2 7.6-4.2L21 12l-9 5-9-5 1.4-1Zm0 4.3 7.6-4.2L21 16l-9 5-9-5 1.4-.7Z',
        'book'     => 'M6 2h13v20H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2Zm0 2v14h11V4Z',
        'building' => 'M4 21V3h10v6h6v12ZM6 5v14h6V5Zm8 6v8h4v-8ZM7.5 7h3v2h-3Zm0 4h3v2h-3Zm0 4h3v2h-3Z',
        'poll'     => 'M4 3h16a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Zm3 12h2v3H7Zm4-6h2v9h-2Zm4 3h2v6h-2Z',
        'quiz'     => 'M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm0 18a8 8 0 1 1 8-8 8 8 0 0 1-8 8Zm-1-3h2v2h-2Zm1.2-9.5A3.2 3.2 0 0 0 9 10.7h2a1.2 1.2 0 1 1 2 1.1c-.7.5-1.9 1-1.9 2.6h1.9c0-1 1.9-1.3 1.9-3.2a3.2 3.2 0 0 0-3.7-3.7Z',
        'database' => 'M12 2c4.4 0 8 1.3 8 3v14c0 1.7-3.6 3-8 3s-8-1.3-8-3V5c0-1.7 3.6-3 8-3Zm0 2c-3.6 0-6 1-6 1s2.4 1 6 1 6-1 6-1-2.4-1-6-1Zm6 4.4C16.6 9 14.5 9.3 12 9.3S7.4 9 6 8.4v3C7.4 12 9.5 12.3 12 12.3s4.6-.3 6-.9Zm0 6C16.6 15 14.5 15.3 12 15.3s-4.6-.3-6-.9v3.9c.3.3 2.6 1.2 6 1.2s5.7-.9 6-1.2Z',
        'user'     => 'M12 12a5 5 0 1 0-5-5 5 5 0 0 0 5 5Zm0 2c-4 0-9 1.9-9 5.3V22h18v-2.7c0-3.4-5-5.3-9-5.3Z',
        'info'     => 'M12 2a10 10 0 1 0 10 10A10 10 0 0 0 12 2Zm1 15h-2v-6h2Zm0-8h-2V7h2Z',
    ];

    /**
     * Navigasi siap gambar untuk halaman Inertia.
     *
     * Nama rute diubah jadi URL di sini, sebab helper rute Laravel tidak
     * ada di peramban.
     */
    public static function untukInertia(int $tahunAktif, array $daftarTahun): array
    {
        $tabs = [];
        foreach (self::TABS as [$rute, $label, $ikon]) {
            // Rute yang belum terdaftar dilewati, bukan membuat halaman
            // meledak — katalog ini dan daftar rute bisa berbeda saat modul
            // sedang dikerjakan.
            if (!\Illuminate\Support\Facades\Route::has($rute)) continue;

            $tabs[] = [
                'label'   => $label,
                'url'     => route($rute),
                'ikon'    => self::IKON[$ikon] ?? '',
                'aktif'   => request()->routeIs($rute),
                'inertia' => RuteInertia::ada($rute),
            ];
        }

        return [
            'tabs'   => $tabs,
            'tahun'  => $tahunAktif,
            'daftarTahun' => array_values(array_unique(
                $daftarTahun ?: [$tahunAktif]
            )),
        ];
    }

    /** Daftar periode yang pernah dibuat, terbaru lebih dulu. */
    public static function daftarTahun(): array
    {
        return TpkkpAssessment::orderByDesc('tahun')->pluck('tahun')->all();
    }
}
