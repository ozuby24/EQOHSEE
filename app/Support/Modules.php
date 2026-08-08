<?php

namespace App\Support;

/**
 * Katalog modul EQOHSEE.
 * Dibuat sebagai kelas (bukan partial Blade) karena @include punya scope
 * terpisah — variabel dari partial tidak terbawa ke view induk.
 */
class Modules
{
    public static function all(): array
    {
        return [
            ['nama' => 'LMS — Learning Center', 'status' => 'aktif',
             'ket'  => 'Pelatihan, kuis, evaluasi SOP, dan sertifikat digital ber-barcode.',
             'ikon' => 'M12 14l9-5-9-5-9 5 9 5z M12 14l6.16-3.42A12 12 0 0112 21a12 12 0 01-6.16-10.42L12 14z'],

            ['nama' => 'Safety Maturity Level', 'status' => 'aktif',
             'ket'  => 'Penilaian tingkat kematangan keselamatan: 194 item · 24 parameter · 4 indikator, lengkap Kalkulator Slovin.',
             'ikon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],

            ['nama' => 'Hazard Report & Inspeksi', 'status' => 'aktif',
             'ket'  => 'Pelaporan bahaya lapangan dan inspeksi rutin dengan tindak lanjut berjenjang, KPI per jabatan, dan ekspor siap cetak.',
             'ikon' => 'M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'],

            ['nama' => 'Keselamatan Operasi (KO)', 'status' => 'aktif',
             'ket'  => 'Kelayakan objek, jadwal perawatan, alat pengaman, kajian teknis, dan tenaga teknis bersertifikat.',
             'ikon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z'],

            ['nama' => 'SMKP Audit', 'status' => 'aktif',
             'ket'  => 'Audit 7 elemen SMKP Minerba sesuai Kepdirjen 185.K/2019: penilaian per kriteria, temuan berjenjang, dan laporan siap cetak.',
             'ikon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],

            ['nama' => 'Sistem Informasi Gudang & Penyimpanan', 'status' => 'segera',
             'ket'  => 'Pengelolaan stok, penerimaan, pengeluaran, dan penyimpanan material.',
             'ikon' => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],

            ['nama' => 'ISO & Dokumen', 'status' => 'segera',
             'ket'  => 'Kendali dokumen, revisi, dan kepatuhan standar.',
             'ikon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.59a1 1 0 01.7.29l4.42 4.42a1 1 0 01.29.7V19a2 2 0 01-2 2z'],
        ];
    }
}
