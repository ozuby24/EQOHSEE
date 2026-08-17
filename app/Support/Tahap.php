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
    public const OHSE       = 'ohse';

    /**
     * Urutan meja, dari yang pertama.
     *
     * Urutannya untuk DIBACA, bukan untuk dipaksakan: paraf boleh
     * dibubuhkan tidak berurutan. Memaksakan urutannya akan membuat
     * pengajuan yang kepala departemennya kebetulan melihat lebih dulu
     * tertahan menunggu paraf yang secara isi tidak menambah apa pun.
     *
     * @var array<string,array{label:string, penentu:bool, terang:string}>
     */
    public const RANTAI = [
        self::ATASAN => [
            'label'   => 'Atasan langsung',
            'penentu' => false,
            'terang'  => 'Membenarkan pekerjaan dan kebutuhannya',
        ],
        self::DEPARTEMEN => [
            'label'   => 'Kepala departemen',
            'penentu' => false,
            'terang'  => 'Mengetahui pengajuan dari departemennya',
        ],
        self::OHSE => [
            'label'   => 'OHSE',
            'penentu' => true,
            'terang'  => 'Memutuskan — hanya tahap ini yang menerbitkan',
        ],
    ];

    /** @return list<string> */
    public static function kode(): array
    {
        return array_keys(self::RANTAI);
    }

    /** Tahap yang boleh diparaf; tahap penentu tidak diparaf, ia diputus. */
    public static function dapatDiparaf(string $tahap): bool
    {
        return isset(self::RANTAI[$tahap]) && !self::RANTAI[$tahap]['penentu'];
    }

    public static function label(string $tahap): string
    {
        return self::RANTAI[$tahap]['label'] ?? $tahap;
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
