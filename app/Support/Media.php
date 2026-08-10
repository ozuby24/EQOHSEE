<?php

namespace App\Support;

/**
 * Berkas gambar dan video halaman depan.
 *
 * Halaman depan dirancang untuk foto dan video tambang sungguhan, tetapi
 * tetap harus utuh sebelum berkasnya ada. Karena itu tiap slot diperiksa
 * keberadaannya di sini: bila berkasnya sudah ditaruh, halaman memakainya;
 * bila belum, panorama SVG yang dipakai sebagai gantinya.
 *
 * Dengan begitu menambah foto cukup menyalin berkas ke public/media —
 * tidak perlu menyunting Blade, dan halaman tidak pernah menampilkan
 * gambar rusak.
 */
final class Media
{
    /** Letak seluruh berkas media halaman depan, relatif terhadap public/. */
    public const AKAR = 'media';

    /**
     * Video latar hero.
     *
     * Hanya dipasang di layar lebar. Di ponsel gambar diam yang dipakai —
     * memutar video belasan megabita lewat jaringan site tambang bukan
     * kesan mewah, melainkan halaman yang tidak kunjung muncul.
     */
    public const HERO_VIDEO  = 'hero/tambang.mp4';
    public const HERO_POSTER = 'hero/tambang.jpg';

    /**
     * Galeri lapangan.
     *
     * Tiap butir boleh berupa gambar saja atau gambar dengan videonya.
     * Yang punya video ditandai tombol putar; sisanya tampil sebagai foto.
     */
    public static function galeri(): array
    {
        return [
            [
                'judul' => 'Inspeksi & Observasi',
                'ket'   => 'Pemeriksaan kondisi lapangan langsung dari perangkat.',
                'gambar'=> 'galeri/inspeksi.jpg',
                'video' => 'galeri/inspeksi.mp4',
                'aspek' => 'safety',
            ],
            [
                'judul' => 'Operasional Tambang',
                'ket'   => 'Kegiatan harian di area penambangan.',
                'gambar'=> 'galeri/operasional.jpg',
                'video' => 'galeri/operasional.mp4',
                'aspek' => 'engineering',
            ],
            [
                'judul' => 'Pengendalian Risiko',
                'ket'   => 'Identifikasi bahaya dan penetapan pengendaliannya.',
                'gambar'=> 'galeri/risiko.jpg',
                'video' => null,
                'aspek' => 'occhealth',
            ],
            [
                'judul' => 'Safety Briefing',
                'ket'   => 'Pengarahan keselamatan sebelum giliran kerja dimulai.',
                'gambar'=> 'galeri/briefing.jpg',
                'video' => null,
                'aspek' => 'quality',
            ],
            [
                'judul' => 'Kinerja Energi',
                'ket'   => 'Pemantauan konsumsi bahan bakar dan listrik alat.',
                'gambar'=> 'galeri/energi.jpg',
                'video' => null,
                'aspek' => 'energy',
            ],
            [
                'judul' => 'Reklamasi & Lingkungan',
                'ket'   => 'Penanganan lahan bekas tambang dan mutu lingkungan.',
                'gambar'=> 'galeri/lingkungan.jpg',
                'video' => null,
                'aspek' => 'environment',
            ],
        ];
    }

    /** Berkas ada di public/media? */
    public static function ada(?string $jalur): bool
    {
        return $jalur !== null && $jalur !== ''
            && is_file(public_path(self::AKAR.'/'.ltrim($jalur, '/')));
    }

    /** URL berkas bila ada, null bila belum ditaruh. */
    public static function url(?string $jalur): ?string
    {
        return self::ada($jalur) ? asset(self::AKAR.'/'.ltrim($jalur, '/')) : null;
    }

    /** Hero memakai video hanya bila berkasnya benar-benar tersedia. */
    public static function heroVideo(): ?string { return self::url(self::HERO_VIDEO); }
    public static function heroPoster(): ?string { return self::url(self::HERO_POSTER); }

    /**
     * Logo perusahaan pengguna.
     *
     * Sengaja dibaca dari folder, bukan ditulis di berkas ini. Memajang
     * logo perusahaan berarti menyatakan mereka memakai platform ini, dan
     * pernyataan itu hanya boleh dibuat oleh yang menaruh logonya —
     * bukan oleh kode yang menebak.
     */
    public static function klien(): array
    {
        $folder = public_path(self::AKAR.'/klien');
        if (!is_dir($folder)) return [];

        $berkas = glob($folder.'/*.{svg,png,webp,jpg}', GLOB_BRACE) ?: [];
        sort($berkas);

        return array_map(fn ($b) => [
            'nama' => ucwords(str_replace(['-', '_'], ' ', pathinfo($b, PATHINFO_FILENAME))),
            'url'  => asset(self::AKAR.'/klien/'.basename($b)),
        ], $berkas);
    }
}
