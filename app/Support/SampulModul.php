<?php

namespace App\Support;

/**
 * Foto sampul halaman awal tiap modul.
 *
 * Satu foto untuk seluruh modul membuat kedua puluh halaman awalnya
 * terbaca sebagai halaman yang sama: orang yang berpindah dari Peledakan
 * ke Penirisan tidak mendapat tanda apa pun bahwa ia sudah pindah, dan
 * satu-satunya pembeda tinggal judul kecil di pojok. Karena itu
 * pemetaannya di sini disusun menurut ISI fotonya — pelabuhan untuk yang
 * mengurus muatan dan persediaan, jalan angkut untuk yang mengurus
 * armada, panorama pit untuk yang mengurus rancangan dan lahan.
 *
 * Berkasnya dibaca lewat Media, jadi modul yang fotonya belum ditaruh
 * tidak menampilkan gambar rusak — sampulnya hanya tidak tergambar, dan
 * halamannya tetap utuh.
 *
 * Tiap berkas punya pasangan .webp yang dipakai lebih dulu bila
 * peramban menerimanya. Aslinya PNG 2,4 MB per gambar; di jaringan site
 * tambang enam sampul sebesar itu bukan kesan mewah melainkan halaman
 * yang tidak kunjung muncul.
 */
final class SampulModul
{
    /** Folder sampul di bawah public/media. */
    private const FOLDER = 'sampul';

    /**
     * Kunci modul (lihat App\Support\Menu) → nama berkas tanpa akhiran.
     *
     * Yang tidak terdaftar memakai SAMPUL_BAWAAN. Menambah modul baru
     * karena itu tidak pernah membuat halamannya kosong — ia hanya
     * memakai panorama umum sampai fotonya sendiri dipilih.
     */
    private const PETA = [
        // Ringkasan lintas modul: panorama, bukan satu kegiatan tertentu.
        'dasbor' => 'pit-panorama',

        // Mengurus orang dan kepatuhannya: ada manusia di dalam fotonya.
        'personalia'  => 'pengawasan-pit',
        'lms'         => 'pengawasan-pit',
        'tpkkp'       => 'pengawasan-pit',
        'smkp'        => 'pengawasan-pit',
        'miners'      => 'pengawasan-pit',
        'hris'        => 'pengawasan-pit',
        'investigasi' => 'pengawasan-pit',
        'pjp'         => 'pelabuhan-muat',

        // Mengurus bahaya di lapangan.
        'hazrep' => 'jalan-angkut',
        'izin'   => 'jalan-angkut',
        'ko'     => 'pit-pemuatan',

        // Mengurus armada dan perpindahan material.
        'angkutan'    => 'jalan-angkut',
        'maintenance' => 'jalan-angkut',
        'operasi'     => 'pit-pemuatan',
        'peledakan'   => 'pit-panorama',

        // Mengurus rancangan, lahan, dan air.
        'meh'         => 'pit-panorama',
        'geoteknik'   => 'pit-panorama',
        'lingkungan'  => 'pit-panorama',
        'konservasi'  => 'pit-pemuatan',
        'air'         => 'pit-pemuatan',

        // Mengurus barang, biaya, dan berkas.
        'gudang'    => 'pelabuhan-muat',
        'pembelian' => 'pelabuhan-muat',
        'biaya'     => 'pelabuhan-senja',
        'energi'  => 'pelabuhan-senja',
        'dokumen' => 'pelabuhan-senja',
        'admin'   => 'pelabuhan-senja',
    ];

    private const SAMPUL_BAWAAN = 'pit-panorama';

    /**
     * Keterangan tempat tiap foto diambil.
     *
     * Dipakai sebagai teks alternatif dan sebagai keterangan kecil pada
     * sampulnya. Foto tanpa keterangan pada halaman resmi mudah
     * disangka foto situs perusahaan yang bersangkutan — padahal ini
     * rekaman umum, dan menyebutnya membuat perbedaannya jujur.
     */
    private const KETERANGAN = [
        'pelabuhan-muat'  => 'Pemuatan di dermaga curah',
        'jalan-angkut'    => 'Jalan angkut tambang',
        'pelabuhan-senja' => 'Terminal dan konveyor pemuatan',
        'pit-pemuatan'    => 'Pemuatan di area penambangan',
        'pit-panorama'    => 'Panorama area penambangan',
        'pengawasan-pit'  => 'Pengawasan area penambangan',
    ];

    /** Nama berkas sampul sebuah modul, tanpa folder maupun akhiran. */
    public static function nama(string $kunciModul): string
    {
        return self::PETA[$kunciModul] ?? self::SAMPUL_BAWAAN;
    }

    /**
     * Sampul siap pakai, atau null bila berkasnya belum ada.
     *
     * `webp` boleh null sendirian: peramban lama tetap mendapat jpg-nya.
     * Yang menentukan sampulnya tergambar atau tidak adalah jpg-nya.
     */
    public static function untuk(string $kunciModul): ?array
    {
        $nama = self::nama($kunciModul);

        $jpg = Media::url(self::FOLDER."/{$nama}.jpg");

        if ($jpg === null) return null;

        return [
            'gambar'     => $jpg,
            'webp'       => Media::url(self::FOLDER."/{$nama}.webp"),
            'keterangan' => self::KETERANGAN[$nama] ?? 'Area operasi tambang',
        ];
    }

    /** Seluruh nama berkas yang dipakai — dibaca uji kelengkapan berkas. */
    public static function berkasDipakai(): array
    {
        return array_values(array_unique(
            array_merge(array_values(self::PETA), [self::SAMPUL_BAWAAN])
        ));
    }

    /** Peta kunci modul → nama sampul, untuk uji sebaran. */
    public static function peta(): array
    {
        return self::PETA;
    }
}
