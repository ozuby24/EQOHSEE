<?php

namespace App\Support;

use App\Models\User;

/**
 * Rantai paraf yang terlihat, dan satu tahap yang benar-benar memutuskan.
 *
 * Pengajuan melewati beberapa meja sebelum sampai ke OHSE. Rantai itu
 * digambar supaya pemohon dapat melihat pengajuannya sedang ada di
 * siapa — pertanyaan yang paling sering ditanyakan, dan yang tanpa
 * gambar rantainya hanya terjawab dengan bertanya keliling.
 *
 * YANG MEMUTUSKAN HANYA OHSE. Meja sebelumnya membubuhkan paraf: tanda
 * sudah melihat, bukan izin. Dua sifat menjaga bedanya tetap nyata:
 *
 *   Paraf tidak menerbitkan.  Ia tidak menyentuh kolom status sama
 *                             sekali, jadi tidak ada jalan bagi paraf
 *                             untuk meloloskan orang di gerbang.
 *
 *   Paraf tidak menahan.      OHSE dapat memutuskan walau paraf di
 *                             atasnya belum ada. Bila ia menahan, meja
 *                             sebelumnya punya kuasa memveto — persis
 *                             yang tidak terjadi di lapangan — dan
 *                             pengajuan mandek di meja yang orangnya
 *                             sedang cuti.
 *
 * YANG BELUM DIPARAF TETAP DISEBUT, bukan disembunyikan. OHSE yang
 * menyetujui pengajuan yang belum dilihat atasannya boleh saja
 * melakukannya, tetapi harus tahu bahwa itulah yang sedang ia lakukan.
 */
final class Tahap
{
    public const ATASAN     = 'atasan';
    public const DEPARTEMEN = 'departemen';
    public const PARAMEDIS  = 'paramedis';
    public const OHSE       = 'ohse';
    public const KTT        = 'ktt';

    /**
     * Urutan meja, dari yang pertama.
     *
     * Urutannya untuk DIBACA, bukan untuk dipaksakan: paraf boleh
     * dibubuhkan tidak berurutan. Memaksakan urutannya akan membuat
     * pengajuan yang kepala departemennya kebetulan melihat lebih dulu
     * tertahan menunggu paraf yang secara isi tidak menambah apa pun.
     *
     * RANTAINYA BERBEDA PER MODUL, dan itu bukan kerumitan yang dapat
     * disatukan. Pengajuan MCU melewati paramedis — dialah yang membaca
     * hasil pemeriksaannya, dan tidak ada meja lain yang dapat
     * menggantikannya — lalu ditutup Kepala Teknik Tambang. Induksi
     * tidak: ia diselenggarakan OHSE sendiri, jadi rantai tiga meja di
     * atasnya hanyalah upacara. Menyeragamkan keduanya berarti salah
     * satunya pasti keliru.
     *
     * @var array<string,array<string,array{label:string, penentu:bool, terang:string}>>
     */
    public const RANTAI_MODUL = [
        'mcu' => [
            self::PARAMEDIS => [
                'label' => 'Paramedis',
                'penentu' => false,
                'terang' => 'Membaca hasil pemeriksaan dan menyimpulkan kelayakannya',
            ],
            self::OHSE => [
                'label' => 'OHSE',
                'penentu' => true,
                'terang' => 'Memutuskan — hanya tahap ini yang meloloskan',
            ],
            self::KTT => [
                'label' => 'Kepala Teknik Tambang',
                'penentu' => false,
                'terang' => 'Mengetahui, sebagai penanggung jawab keselamatan tambang',
            ],
        ],

        'induksi' => [
            self::OHSE => [
                'label' => 'OHSE',
                'penentu' => true,
                'terang' => 'Menyelenggarakan sekaligus memutuskan',
            ],
        ],

        'kartu' => [
            self::ATASAN => [
                'label' => 'Atasan langsung',
                'penentu' => false,
                'terang' => 'Membenarkan pekerjaan dan kebutuhannya',
            ],
            self::DEPARTEMEN => [
                'label' => 'Kepala departemen',
                'penentu' => false,
                'terang' => 'Mengetahui pengajuan dari departemennya',
            ],
            self::OHSE => [
                'label' => 'OHSE',
                'penentu' => true,
                'terang' => 'Memutuskan — hanya tahap ini yang menerbitkan',
            ],
        ],
    ];

    /** Rantai baku bagi modul yang belum menyebut rantainya sendiri. */
    public const RANTAI = self::RANTAI_MODUL['kartu'];

    /** @return array<string,array{label:string, penentu:bool, terang:string}> */
    public static function rantai(?string $modul = null): array
    {
        return self::RANTAI_MODUL[$modul] ?? self::RANTAI;
    }

    /** @return list<string> */
    public static function kode(): array
    {
        /* Seluruh kode dari SEMUA rantai. Dipakai memvalidasi kiriman
           formulir, dan pembatasan per modulnya dikerjakan
           dapatDiparaf() beserta rantai modelnya — bukan di sini. */
        return array_values(array_unique(array_merge(
            ...array_map('array_keys', array_values(self::RANTAI_MODUL))
        )));
    }

    /** Tahap yang boleh diparaf; tahap penentu tidak diparaf, ia diputus. */
    public static function dapatDiparaf(string $tahap, ?string $modul = null): bool
    {
        $r = self::rantai($modul);

        return isset($r[$tahap]) && !$r[$tahap]['penentu'];
    }

    public static function label(string $tahap): string
    {
        foreach (self::RANTAI_MODUL as $rantai) {
            if (isset($rantai[$tahap])) return $rantai[$tahap]['label'];
        }

        return $tahap;
    }

    /**
     * Siapa yang memutuskan: OHSE, dan administrator.
     *
     * Administrator ikut disebut bukan sebagai kelonggaran melainkan
     * sebagai jalan keluar dari kebuntuan: pemasangan yang belum menunjuk
     * seorang pun sebagai OHSE tidak boleh berarti tidak ada satu kartu
     * pun yang dapat diterbitkan selamanya.
     *
     * KTT sengaja TIDAK disebut, meskipun ia menandatangani hampir
     * seluruh dokumen lain di sistem ini. Pemakainya menyatakan tegas
     * bahwa yang memegang persetujuan penuh hanya tim OHSE, dan
     * menambahkan KTT "karena biasanya begitu" akan diam-diam
     * memperluas wewenang yang justru sedang dipersempit.
     */
    public static function penentu(?User $u): bool
    {
        return (bool) $u?->isAdmin() || (bool) $u?->isOhse();
    }
}
