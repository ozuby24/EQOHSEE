<?php

namespace App\Support;

/**
 * Pustaka ikon garis (stroke) untuk kartu statistik.
 *
 * Hanya menyimpan atribut `d` dari <path>, bukan SVG utuh, supaya pemanggil
 * bebas menentukan ukuran, warna, dan ketebalan garis. Semua ikon digambar
 * pada kanvas 24×24 dengan gaya garis yang sama sehingga tampak satu keluarga.
 *
 * Dipakai agar kartu angka tidak tampil polos — angka tanpa penanda visual
 * sulit dipindai sekilas ketika satu panel memuat belasan kartu.
 */
class Ikon
{
    /** Ikon bawaan bila label tidak dikenal: lingkaran info. */
    public const BAWAAN = 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 8h.01M11 12h1v4h1';

    /**
     * Peta label statistik → gambar ikon.
     *
     * Kunci dicocokkan tanpa peduli besar-kecil huruf secara sebagian,
     * sehingga "Percobaan kuis" dan "Percobaan SOP" tidak perlu ditulis
     * dua kali. Bila beberapa kunci cocok, yang TERPANJANG menang — urutan
     * penulisan di larik ini tidak boleh memengaruhi hasil.
     */
    private const PETA = [
        // Inti
        'pengguna'    => 'M9 8a3.2 3.2 0 100 6.4 3.2 3.2 0 000-6.4zM3.5 20a5.5 5.5 0 0111 0M17 8.5a3 3 0 010 5.4M20.5 20a5 5 0 00-3-4.6',
        'perusahaan'  => 'M3 21V8l7-4 7 4v13M17 21V11l4 2v8M8 21v-4h4v4',
        'log'         => 'M4 6h16M4 12h16M4 18h10',
        'aktivitas'   => 'M3 12h4l3 8 4-16 3 8h4',

        // LMS
        'kursus'      => 'M12 4 3 8l9 4 9-4-9-4zM7 10.5V15c0 1.3 2.7 2.3 5 2.3s5-1 5-2.3v-4.5',
        'modul'       => 'M4 5h7v6H4zM13 5h7v6h-7zM4 13h7v6H4zM13 13h7v6h-7z',
        'materi'      => 'M7 3h7l4 4v14H7a1 1 0 01-1-1V4a1 1 0 011-1zM14 3v4h4M9.5 12h5M9.5 15.5h5',
        'kuis'        => 'M9 9a3 3 0 116 0c0 2-3 2.2-3 4M12 17h.01M12 3a9 9 0 100 18 9 9 0 000-18z',
        'pendaftaran' => 'M16 11V7a4 4 0 00-8 0v4M5 11h14v9a1 1 0 01-1 1H6a1 1 0 01-1-1z',
        'percobaan'   => 'M12 3a9 9 0 100 18 9 9 0 000-18zM12 7v5l3 2',
        'sertifikat'  => 'M12 3a5 5 0 100 10 5 5 0 000-10zM8.5 13 7 21l5-3 5 3-1.5-8',
        'penanda'     => 'M12 3l2 4 4 .6-3 3 .8 4-3.8-2-3.8 2 .8-4-3-3 4-.6zM6 21s2-4 6-4 6 4 6 4',
        'prosedur'    => 'M7 3h7l4 4v14H7a1 1 0 01-1-1V4a1 1 0 011-1zM14 3v4h4M9.5 13l1.5 1.5 3-3',
        'evaluasi'    => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'berita'      => 'M4 5h13a1 1 0 011 1v13H5a1 1 0 01-1-1zM18 9h2v9a1 1 0 01-2 0zM7 9h7M7 12.5h7M7 16h4',

        // Safety Maturity / PTPKKP
        'penilaian'   => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
        'responden'   => 'M8 10h8M8 13.5h5M21 12a8 8 0 01-8 8H7l-4 3v-4.5A8 8 0 1121 12z',
        'dinilai'     => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',

        // Hazard & Inspeksi
        'bahaya'      => 'M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
        'hazard'      => 'M12 9v4m0 4h.01M10.29 3.86 1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z',
        'inspeksi'    => 'M11 4a7 7 0 100 14 7 7 0 000-14zM21 21l-5-5',
        'temuan'      => 'M11 4a7 7 0 100 14 7 7 0 000-14zM21 21l-5-5M11 8v3M11 14h.01',

        // Keselamatan Operasi
        'objek'       => 'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10',
        'perawatan'   => 'M14.7 6.3a4 4 0 01-5 5L5 16v3h3l4.7-4.7a4 4 0 015-5z',
        'pengaman'    => 'M12 3l7.5 4v5c0 4.4-3.1 8.5-7.5 9.7C7.6 20.5 4.5 16.4 4.5 12V7L12 3z',
        'tenaga'      => 'M9 8a3.2 3.2 0 100 6.4 3.2 3.2 0 000-6.4zM3.5 20a5.5 5.5 0 0111 0M17 8.5a3 3 0 010 5.4M20.5 20a5 5 0 00-3-4.6',
        'kajian'      => 'M7 3h7l4 4v14H7a1 1 0 01-1-1V4a1 1 0 011-1zM14 3v4h4M9.5 12h5M9.5 15.5h3',

        // Audit SMKP
        'audit'       => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'kriteria'    => 'M4 6h16M4 12h16M4 18h16M2.5 6h.01M2.5 12h.01M2.5 18h.01',
        'elemen'      => 'M4 5h7v6H4zM13 5h7v6h-7zM4 13h7v6H4zM13 13h7v6h-7z',
    ];

    /** Gambar ikon untuk sebuah label statistik. */
    public static function untuk(string $label): string
    {
        $l = mb_strtolower(trim($label));

        // Cocokkan utuh dulu agar label spesifik menang atas pencocokan sebagian.
        if (isset(self::PETA[$l])) return self::PETA[$l];

        // Kunci terpanjang menang: "Percobaan kuis" harus memilih "percobaan"
        // (9 huruf) alih-alih "kuis" (4), berapa pun urutan penulisannya.
        $terbaik = null; $panjang = 0;
        foreach (self::PETA as $kunci => $d) {
            if (mb_strlen($kunci) > $panjang && str_contains($l, $kunci)) {
                $terbaik = $d; $panjang = mb_strlen($kunci);
            }
        }

        return $terbaik ?? self::BAWAAN;
    }
}
