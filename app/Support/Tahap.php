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
                'peran' => self::PARAMEDIS,
                'terang' => 'Membaca hasil pemeriksaan dan menyimpulkan kelayakannya',
            ],
            self::OHSE => [
                'label' => 'OHSE',
                'penentu' => true,
                'peran' => self::OHSE,
                'terang' => 'Memutuskan — hanya tahap ini yang meloloskan',
            ],
            self::KTT => [
                'label' => 'Kepala Teknik Tambang',
                'penentu' => false,
                'peran' => self::KTT,
                'terang' => 'Mengetahui, sebagai penanggung jawab keselamatan tambang',
            ],
        ],

        'induksi' => [
            self::OHSE => [
                'label' => 'OHSE',
                'penentu' => true,
                'peran' => self::OHSE,
                'terang' => 'Menyelenggarakan sekaligus memutuskan',
            ],
        ],

        'kartu' => [
            self::ATASAN => [
                'label' => 'Atasan langsung',
                'penentu' => false,
                'peran' => null,
                'terang' => 'Membenarkan pekerjaan dan kebutuhannya',
            ],
            self::DEPARTEMEN => [
                'label' => 'Kepala departemen',
                'penentu' => false,
                'peran' => null,
                'terang' => 'Mengetahui pengajuan dari departemennya',
            ],
            self::OHSE => [
                'label' => 'OHSE',
                'penentu' => true,
                'peran' => self::OHSE,
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

    /**
     * Peran yang berhak memaraf satu tahap — null bila tak dapat dijaga.
     *
     * TIDAK SETIAP TAHAP DAPAT DIJAGA, dan menyamakannya akan keliru.
     * "Paramedis" dan "Kepala Teknik Tambang" adalah JABATAN: satu orang
     * di seluruh perusahaan memegangnya, dan siapa dia dapat ditanyakan
     * kepada sistem. "Atasan langsung" dan "Kepala departemen" bukan
     * jabatan melainkan HUBUNGAN — atasan siapa, kepala departemen mana —
     * dan EQOHSEE tidak menyimpan garis pelaporan sama sekali.
     *
     * Membuatkan peran global bernama "atasan" akan menjadikan satu orang
     * atasan seluruh perusahaan; menjaganya dengan peran itu berarti
     * memasang penjagaan yang tampak ketat tetapi tidak menjaga apa pun.
     * Yang jujur adalah membiarkannya terbuka dan MENCATAT siapa yang
     * memaraf — dan itu memang sudah dikerjakan.
     */
    public static function peran(string $tahap, ?string $modul = null): ?string
    {
        return self::rantai($modul)[$tahap]['peran'] ?? null;
    }

    /**
     * Orang ini memegang peran itu?
     *
     * Administrator memegang semuanya. Sama alasannya dengan penentu():
     * pemasangan yang belum menunjuk seorang pun sebagai paramedis tidak
     * boleh berarti tidak ada satu pun pengajuan MCU yang dapat berjalan
     * selamanya.
     */
    public static function berperan(?User $u, ?string $peran): bool
    {
        if (!$u) return false;
        if ($peran === null) return true;      // tahap yang memang tidak dijaga
        if ($u->isAdmin()) return true;

        return match ($peran) {
            self::PARAMEDIS => $u->isParamedis(),
            self::OHSE      => $u->isOhse(),
            self::KTT       => $u->isKtt(),
            default         => false,
        };
    }

    /**
     * Mengapa orang ini TIDAK dapat memaraf tahap itu.
     *
     * Ada karena sebelum ini SIAPA PUN dapat memaraf tahap MANA PUN.
     * Seorang admin kontraktor dapat membubuhkan paraf pada tahap
     * "Paramedis", dan yang tercetak pada rantai adalah
     * "Paramedis · <namanya>" — sebuah pernyataan bahwa hasil
     * pemeriksaan sudah dibaca tenaga medis, yang tidak pernah terjadi.
     * Kegagalannya sunyi: parafnya sah menurut basis data, terlihat
     * benar di layar, dan hanya orang yang mengenal namanya yang tahu
     * bahwa ia bukan paramedis.
     *
     * Menyebut sebabnya, bukan sekadar menyembunyikan tombolnya —
     * lihat sebabTakDapatMemutuskan() untuk alasan yang sama.
     *
     * @return ?string null bila ia memang boleh memaraf
     */
    public static function sebabTakDapatMemaraf(?User $u, string $tahap, ?string $modul = null): ?string
    {
        if (!isset(self::rantai($modul)[$tahap])) {
            return 'Tahap ini bukan bagian dari rantai pengajuan tersebut.';
        }

        if (!self::dapatDiparaf($tahap, $modul)) {
            return 'Tahap penentu diputus, bukan diparaf.';
        }

        $peran = self::peran($tahap, $modul);

        if (self::berperan($u, $peran)) return null;

        return match ($peran) {
            self::PARAMEDIS => 'Tahap ini diparaf paramedis. Peran paramedis diberikan '
                              .'lewat Admin → Pengguna → Peran OHSE.',
            self::KTT       => 'Tahap ini diparaf Kepala Teknik Tambang. Perannya diberikan '
                              .'lewat Admin → Pengguna → Peran LMS.',
            self::OHSE      => 'Tahap ini dipegang tim OHSE.',
            default         => 'Anda tidak berhak memaraf tahap ini.',
        };
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

    /**
     * Mengapa orang ini TIDAK dapat memutuskan pengajuan itu.
     *
     * Ada karena kegagalan yang sesungguhnya dilaporkan bukan
     * "penolakannya salah" melainkan "tombolnya tidak ada". Menyembunyikan
     * tombol tanpa keterangan adalah bentuk penolakan yang paling buruk:
     * yang membacanya tidak dapat membedakan antara tidak berhak, sudah
     * diputus, dan sistemnya rusak — dan dugaan yang paling sering
     * diambil adalah yang ketiga.
     *
     * @return ?string null bila ia memang dapat memutuskan
     */
    public static function sebabTakDapatMemutuskan(?User $u, string $status, $diajukanOleh): ?string
    {
        if (!self::penentu($u)) {
            return 'Keputusan dipegang tim OHSE. Peran OHSE diberikan lewat '
                 .'Admin → Pengguna → Peran OHSE.';
        }

        if ($status === Alur::DRAF) {
            return 'Masih draf — belum diajukan, jadi belum ada yang perlu diputuskan.';
        }

        if ($status !== Alur::DIAJUKAN) {
            return 'Sudah diputus ('.(Alur::LABEL[$status] ?? $status).').';
        }

        if ($diajukanOleh !== null && $diajukanOleh === $u?->getKey()) {
            return 'Anda pengajunya sendiri. Pengaju tidak meninjau pekerjaannya '
                 .'sendiri — mintalah anggota OHSE lain memutuskannya.';
        }

        return null;
    }
}
