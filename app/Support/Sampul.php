<?php

namespace App\Support;

/**
 * Foto sampul kursus.
 *
 * Berdiri sebagai kelas supaya dashboard, katalog, dan halaman kursus
 * memakai aturan yang sama. Aturannya sempat ditulis ulang di setiap view,
 * dan yang terjadi persis seperti yang bisa diduga: dashboard memasang
 * rekaman lapangan sementara katalog menampilkan kotak kosong berhuruf,
 * padahal keduanya menampilkan kursus yang sama.
 */
final class Sampul
{
    /** Kategori yang sudah dikenal → rekaman lapangan yang paling mendekati. */
    private const PETA = [
        'keselamatan kerja' => 'galeri/budaya.jpg',
        'operasional'       => 'galeri/operasional.jpg',
        'lingkungan'        => 'galeri/risiko.jpg',
        'kesehatan'         => 'galeri/budaya.jpg',
        'inspeksi'          => 'galeri/inspeksi.jpg',
        'wajib'             => 'galeri/budaya.jpg',
        'risiko'            => 'galeri/risiko.jpg',
    ];

    /** Dipakai bergiliran untuk kategori di luar daftar. */
    private const GALERI = [
        'galeri/operasional.jpg',
        'galeri/budaya.jpg',
        'galeri/inspeksi.jpg',
        'galeri/risiko.jpg',
    ];

    /**
     * Alamat foto sampul sebuah kursus, atau null bila berkasnya belum ada.
     *
     * Kursus yang punya gambar sendiri memakainya. Sisanya memakai rekaman
     * lapangan menurut kategorinya; kategori di luar daftar dibagi rata
     * menurut id, bukan diarahkan ke satu foto cadangan — satu foto untuk
     * semua membuat dua kartu bersebelahan tampak seperti kursus yang sama.
     * Memakai id membuat pilihannya tetap sama setiap kali halaman dimuat.
     */
    public static function untuk($course): ?string
    {
        if ($course?->image) return asset('storage/'.$course->image);

        $k = mb_strtolower(trim((string) ($course->category ?? '')));

        foreach (self::PETA as $cari => $berkas) {
            if ($k !== '' && str_contains($k, $cari)) return Media::url($berkas);
        }

        return Media::url(self::GALERI[($course?->id ?? 0) % count(self::GALERI)]);
    }
}
