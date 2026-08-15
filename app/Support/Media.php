<?php

namespace App\Support;

/**
 * Berkas gambar dan video halaman depan.
 *
 * Halaman depan dibangun di atas rekaman tambang sungguhan, tetapi tetap
 * harus utuh sebelum berkasnya ada. Karena itu tiap slot diperiksa
 * keberadaannya di sini: bila berkasnya sudah ditaruh, halaman memakainya;
 * bila belum, panorama SVG yang dipakai sebagai gantinya.
 *
 * Dengan begitu menambah rekaman cukup menyalin berkas ke public/media —
 * tidak perlu menyunting Blade, dan halaman tidak pernah menampilkan
 * gambar rusak.
 */
final class Media
{
    /** Letak seluruh berkas media, relatif terhadap public/. */
    public static function akar(): string
    {
        return trim((string) config('media.akar', 'media'), '/');
    }

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
     * Latar halaman masuk dan pendaftaran.
     *
     * Sebelumnya sebuah foto 900 piksel dipakai untuk panel setinggi layar
     * penuh. Diperbesar tiga kali lipat pada layar berkerapatan ganda,
     * fotonya pecah — dan itu adalah layar pertama yang dilihat pengguna
     * baru. Rekaman 1280 piksel yang bergerak menutupi jarak itu jauh
     * lebih baik daripada foto diam yang diregangkan.
     */
    public const MASUK_VIDEO  = 'hero/masuk.mp4';
    public const MASUK_POSTER = 'hero/masuk.jpg';

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
                'ket'   => 'Pemeriksaan kondisi unit dan area kerja, langsung dari perangkat.',
                'gambar'=> 'galeri/safety.jpg',
                'video' => 'galeri/safety.mp4',
                'aspek' => 'safety',
            ],
            [
                'judul' => 'Perencanaan & Survei Tambang',
                'ket'   => 'Rancangan pit dan pengukuran kemajuan tambang.',
                'gambar'=> 'galeri/engineering.jpg',
                'video' => 'galeri/engineering.mp4',
                'aspek' => 'engineering',
            ],
            [
                'judul' => 'Pemantauan Pajanan Kerja',
                'ket'   => 'Pengukuran faktor bahaya di lingkungan kerja.',
                'gambar'=> 'galeri/occhealth.jpg',
                'video' => 'galeri/occhealth.mp4',
                'aspek' => 'occhealth',
            ],
            [
                'judul' => 'Higiene Industri',
                'ket'   => 'Pengukuran pajanan pada sumbernya, sebelum sampai ke pekerja.',
                'gambar'=> 'galeri/hygiene.jpg',
                'video' => 'galeri/hygiene.mp4',
                'aspek' => 'hygiene',
            ],
            [
                'judul' => 'Pengujian Mutu',
                'ket'   => 'Uji laboratorium sebagai dasar kendali mutu hasil tambang.',
                'gambar'=> 'galeri/quality.jpg',
                'video' => 'galeri/quality.mp4',
                'aspek' => 'quality',
            ],
            [
                'judul' => 'Reklamasi & Lingkungan',
                'ket'   => 'Penanganan lahan bekas tambang dan mutu lingkungan.',
                'gambar'=> 'galeri/environment.jpg',
                'video' => 'galeri/environment.mp4',
                'aspek' => 'environment',
            ],
        ];
    }

    /**
     * Butir galeri yang benar-benar layak ditampilkan.
     *
     * Begitu ada satu butir yang berkasnya lengkap, hanya butir semacam itu
     * yang ditampilkan. Menyandingkan rekaman sungguhan dengan kotak kosong
     * membuat galerinya terbaca sebagai rusak, bukan sebagai belum lengkap —
     * dan menyalin satu berkas baru sudah cukup untuk memunculkannya.
     *
     * Selama belum ada satu pun berkas, seluruh butir tetap ditampilkan
     * sebagai tempat foto, supaya bagian ini tidak hilang sama sekali.
     */
    public static function galeriTerisi(): array
    {
        $semua  = self::galeri();
        $terisi = array_values(array_filter(
            $semua,
            fn ($g) => self::ada($g['gambar']) || self::ada($g['video'] ?? null)
        ));

        return $terisi ?: $semua;
    }

    /** Berkas ada di public/media? */
    public static function ada(?string $jalur): bool
    {
        return $jalur !== null && $jalur !== ''
            && is_file(public_path(self::akar().'/'.ltrim($jalur, '/')));
    }

    /** URL berkas bila ada, null bila belum ditaruh. */
    public static function url(?string $jalur): ?string
    {
        return self::ada($jalur) ? asset(self::akar().'/'.ltrim($jalur, '/')) : null;
    }

    /** Hero memakai video hanya bila berkasnya benar-benar tersedia. */
    public static function heroVideo(): ?string { return self::url(self::HERO_VIDEO); }
    public static function heroPoster(): ?string { return self::url(self::HERO_POSTER); }

    public static function masukVideo(): ?string  { return self::url(self::MASUK_VIDEO); }
    public static function masukPoster(): ?string { return self::url(self::MASUK_POSTER); }

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
        $folder = public_path(self::akar().'/klien');
        if (!is_dir($folder)) return [];

        $berkas = glob($folder.'/*.{svg,png,webp,jpg}', GLOB_BRACE) ?: [];
        sort($berkas);

        return array_map(fn ($b) => [
            'nama' => ucwords(str_replace(['-', '_'], ' ', pathinfo($b, PATHINFO_FILENAME))),
            'url'  => asset(self::akar().'/klien/'.basename($b)),
        ], $berkas);
    }
}
