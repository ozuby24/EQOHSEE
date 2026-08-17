<?php

namespace App\Support;

/**
 * Data apa saja yang menembus batas perusahaan, dan ke arah mana.
 *
 * Ditulis di SATU berkas dengan sengaja. Ini batas keamanan: yang
 * meninjaunya harus dapat melihat seluruh permukaannya sekaligus,
 * bukan memburu penanda yang tersebar di tiga puluh model. Daftar yang
 * tersebar adalah daftar yang bertambah tanpa ada yang memutuskan.
 *
 * TIGA ARAH, DAN HANYA DUA YANG DIBUKA
 *
 *   ke atas   IUJP → IUP    perusahaan jasa membaca acuan induknya
 *   ke bawah  IUP  → IUJP   pemegang izin membaca kinerja kontraktornya
 *   menyamping IUJP ↔ IUJP  TIDAK PERNAH
 *
 * Yang menyamping ditutup rapat, dan itu keputusan yang disengaja: dua
 * kontraktor di bawah satu IUP adalah dua perusahaan yang bersaing
 * memperebutkan pekerjaan yang sama. Membuka laporan bahaya dan daftar
 * kompetensi salah satunya kepada yang lain bukan kelonggaran
 * administratif, melainkan menyerahkan data komersial pesaingnya.
 *
 * Karena scope-nya bekerja dengan menyebut company_id, "tidak
 * menyamping" bukan sesuatu yang perlu dikodekan terpisah: pelebaran
 * hanya menyebut induk SENDIRI dan anak SENDIRI, jadi saudara tidak
 * pernah masuk daftar. Uji serangannya tetap ada — yang tidak diuji
 * bukan yang aman, hanya yang belum ketahuan.
 *
 * YANG TIDAK ADA DI SINI TETAP TERTUTUP. Biaya, gudang, kepegawaian,
 * energi, dan seluruh sisanya tetap milik perusahaan masing-masing.
 * Menambah tabel ke daftar ini adalah keputusan yang harus diambil
 * sadar, bukan akibat sampingan dari menambah modul.
 */
final class LingkupLintas
{
    /**
     * Baris ANAK terlihat oleh INDUKNYA.
     *
     * Kinerja keselamatan kontraktor. Pemegang IUP bertanggung jawab
     * atas keselamatan seluruh pekerjaan di wilayah izinnya — termasuk
     * yang dikerjakan mitra IUJP — dan pertanggungjawaban itu tidak
     * dapat dijalankan tanpa melihat angkanya.
     *
     * @var list<string> nama tabel
     */
    public const KE_INDUK = [
        'hazard_reports',   // laporan bahaya lapangan
        'inspections',      // inspeksi rutin
        'paspor',           // kompetensi, MCU, kartu masuk pekerjanya
    ];

    /**
     * Baris INDUK terlihat oleh ANAK-ANAKNYA.
     *
     * Acuan kerja yang memang harus dipatuhi mitra: prosedur dan
     * dokumen terkendali milik pemegang izin. Kontraktor yang tidak
     * dapat membaca prosedur yang wajib diikutinya akan bekerja dari
     * salinan cetak yang usang — dan itu persis keadaan yang hendak
     * dihilangkan sistem dokumen terkendali.
     *
     * Pelatihan tidak perlu disebut di sini: kursus LMS memang tidak
     * bermilik perusahaan (company_id NULL) sehingga sudah terlihat
     * semua orang.
     *
     * @var list<string> nama tabel
     */
    public const KE_ANAK = [
        'procedures',
        'documents',
    ];

    /** Baris tabel ini terlihat oleh induk perusahaan pemiliknya. */
    public static function keInduk(string $tabel): bool
    {
        return in_array($tabel, self::KE_INDUK, true);
    }

    /** Baris tabel ini terlihat oleh anak-anak perusahaan pemiliknya. */
    public static function keAnak(string $tabel): bool
    {
        return in_array($tabel, self::KE_ANAK, true);
    }
}
