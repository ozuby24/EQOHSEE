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
        'keselamatan kerja' => 'galeri/safety.jpg',
        'operasional'       => 'galeri/engineering.jpg',
        'lingkungan'        => 'galeri/environment.jpg',
        'kesehatan'         => 'galeri/occhealth.jpg',
        'inspeksi'          => 'galeri/safety.jpg',
        'wajib'             => 'galeri/safety.jpg',
        'risiko'            => 'galeri/occhealth.jpg',
        'higiene'           => 'galeri/hygiene.jpg',
        'mutu'              => 'galeri/quality.jpg',
    ];

    /**
     * Dipakai bergiliran untuk kategori di luar daftar.
     *
     * Namanya mengikuti berkas galeri; ketika berkas galeri diganti,
     * daftar ini ikut berganti. Yang tertinggal tidak menimbulkan galat —
     * sampulnya hanya diam-diam kosong, dan dua kursus bersebelahan
     * kembali tampak seperti kursus yang sama.
     */
    private const GALERI = [
        'galeri/engineering.jpg',
        'galeri/safety.jpg',
        'galeri/hygiene.jpg',
        'galeri/occhealth.jpg',
        'galeri/quality.jpg',
        'galeri/environment.jpg',
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
