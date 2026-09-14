<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Pengenalan situs bagi akun yang baru mendaftar.
 *
 * ── Kenapa isinya disusun, bukan ditulis ──
 *
 * Pengenalan fitur adalah salah satu tulisan yang paling cepat basi di
 * sebuah aplikasi, dan basinya tidak pernah terlihat: tidak ada galat,
 * tidak ada uji yang merah, hanya orang baru yang dijanjikan modul yang
 * sudah berganti nama lalu mencarinya sepanjang hari. Maka tidak satu
 * pun nama modul, nama pilar, atau alamat halaman diketik ulang di
 * berkas ini — seluruhnya dibaca dari Pillars::all() dan Menu::untuk(),
 * dua daftar yang memang menjadi sumber kebenarannya.
 *
 * Akibatnya modul yang diganti namanya ikut berganti di pengenalan ini
 * pada permintaan berikutnya juga, tanpa siapa pun perlu ingat.
 *
 * ── Kenapa Menu::untuk($u), bukan Menu::all() ──
 *
 * Menu::untuk() menyaring menurut wewenang orangnya. Memakai all() akan
 * memperkenalkan modul yang, begitu diklik, memulangkan 403 — dan
 * kesan pertama sebuah situs menjadi pintu terkunci yang baru saja
 * ditawarkan kepadanya sendiri.
 */
class Tur
{
    /**
     * Naik satu bila isi pengenalannya berubah cukup berarti untuk
     * ditunjukkan ulang kepada yang sudah pernah melihatnya.
     *
     * Belum dipakai untuk memaksa tur terbuka lagi — menyalakan ulang
     * sambutan bagi orang yang sudah bekerja adalah gangguan, bukan
     * layanan. Disimpan supaya keputusan itu punya tempat ketika suatu
     * saat memang diperlukan.
     */
    public const VERSI = 1;

    /** Pengenalan hanya untuk yang belum pernah menyelesaikannya. */
    public static function perlu(?User $u): bool
    {
        return $u !== null && $u->tur_selesai_pada === null;
    }

    /**
     * Langkah-langkah pengenalan, siap digambar.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function langkah(?User $u): array
    {
        if ($u === null) return [];

        return [
            self::sambutan($u),
            self::pilar(),
            self::modul($u),
            self::langkahPertama(),
        ];
    }

    /**
     * Gelar depan yang dilewati saat menyapa.
     *
     * Bukan kelengkapan yang dikejar melainkan yang benar-benar muncul
     * pada data personalia tambang di Indonesia.
     */
    private const GELAR_DEPAN = [
        'ir', 'dr', 'drg', 'drs', 'dra', 'h', 'hj', 'prof', 'kh', 'ust', 'st',
    ];

    /**
     * Nama panggilan dari nama lengkap.
     *
     * "Selamat datang, Ir. Bambang Sudarsono, S.T., M.M." terbaca seperti
     * surat resmi, bukan sapaan. Tetapi mengambil kata pertama begitu
     * saja menghasilkan "Selamat datang, Ir." — dan sapaan yang menyebut
     * gelarnya saja terdengar seperti aplikasi yang rusak, yang persis
     * bertentangan dengan maksud sambutan ini.
     *
     * Maka gelar di depan dilewati, begitu pula singkatan lain yang
     * berakhir titik. Bila yang tersisa tidak ada — nama yang seluruhnya
     * gelar — nama lengkapnya dipakai apa adanya, sebab menyapa dengan
     * nama panjang masih jauh lebih baik daripada menyapa dengan titik.
     */
    private static function namaSapaan(string $nama): string
    {
        $kata = preg_split('/\s+/', trim($nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($kata as $k) {
            if (in_array(mb_strtolower(rtrim($k, '.')), self::GELAR_DEPAN, true)) continue;
            if (str_ends_with($k, '.')) continue;

            return rtrim($k, ',');
        }

        return trim($nama);
    }

    /**
     * Kalimat pembuka tentang perusahaannya — TIGA keadaan, bukan dua.
     *
     * Semula hanya dua: punya perusahaan, atau tidak. Administrator
     * lintas perusahaan jatuh ke cabang kedua dan disambut dengan
     * "Hubungi administrator bila laporan Anda seharusnya masuk ke
     * sebuah perusahaan" — nasihat yang diberikan kepada satu-satunya
     * orang yang dimaksud nasihat itu, tentang keadaan yang justru
     * disengaja baginya. Kalimat pertama yang dibaca seorang
     * administrator baru karena itu adalah kalimat yang salah,
     * dan salahnya terbaca sebagai aplikasi yang tidak mengenali
     * penggunanya sendiri.
     */
    private static function keterikatan(User $u): string
    {
        if ($u->company?->name) {
            return "Akun Anda terdaftar di bawah {$u->company->name}. Seluruh data yang "
                .'Anda isi tersimpan pada perusahaan itu, dan hanya terlihat oleh '
                .'rekan satu perusahaan.';
        }

        if ($u->isAdmin()) {
            return 'Akun Anda administrator dan tidak terikat satu perusahaan pun — '
                .'itu disengaja. Pemilih perusahaan di bilah atas menentukan '
                .'perusahaan mana yang sedang Anda lihat; "Semua perusahaan" '
                .'menampilkan seluruhnya sekaligus.';
        }

        return 'Akun Anda belum terikat perusahaan mana pun. Hubungi administrator '
            .'bila laporan Anda seharusnya masuk ke sebuah perusahaan.';
    }

    /** Sambutan — menyebut namanya dan perusahaannya. */
    private static function sambutan(User $u): array
    {
        $depan = self::namaSapaan($u->name);

        return [
            'kunci' => 'sambutan',
            'judul' => $depan !== '' ? "Selamat datang, {$depan}" : 'Selamat datang',
            'teks'  => self::keterikatan($u),
            'poin' => [
                ['EQOHSEE mengurus delapan aspek sekaligus',
                 'Satu situs untuk energi, mutu, kesehatan kerja, higiene, keselamatan, '
                 .'lingkungan, keteknikan, dan konservasi minerba.'],
                ['Yang Anda catat dipakai kembali',
                 'Nama, jabatan, dan perusahaan mengisi sendiri kop laporan, sertifikat, '
                 .'dan dokumen yang Anda buat setelahnya.'],
            ],
        ];
    }

    /** Delapan pilar, apa adanya dari registry-nya. */
    private static function pilar(): array
    {
        $daftar = [];

        foreach (Pillars::all() as $kunci => $p) {
            $daftar[] = [
                'kunci'   => $kunci,
                'nama'    => $p['nama'],
                'ket'     => $p['ket'],
                'ringkas' => $p['ringkas'],
                'warna'   => $p['warna'],
                'ikon'    => $p['ikon'],
            ];
        }

        return [
            'kunci' => 'pilar',
            'judul' => 'Delapan aspek yang dijaga',
            'teks'  => 'Tiap aspek punya halamannya sendiri, dan tiap modul di situs ini '
                      .'menopang satu atau beberapa di antaranya.',
            'pilar' => $daftar,
        ];
    }

    /** Modul yang benar-benar dapat dibuka orang ini. */
    private static function modul(User $u): array
    {
        $daftar = [];

        foreach (Menu::untuk($u) as $kunci => $m) {
            $rute = Menu::ruteAwal($m);

            $daftar[] = [
                'kunci'    => $kunci,
                'label'    => $m['label'],
                'semboyan' => $m['semboyan'] ?? '',
                'ikon'     => $m['icon'],
                'url'      => $rute ? route($rute) : null,
            ];
        }

        return [
            'kunci' => 'modul',
            'judul' => 'Modul yang terbuka untuk Anda',
            'teks'  => 'Semuanya berpindah lewat bilah di sisi kiri layar. Pada layar '
                      .'sempit, bilah itu muncul setelah tombol menu ditekan.',
            'modul' => $daftar,
        ];
    }

    /**
     * Tiga hal yang sebaiknya dikerjakan lebih dulu.
     *
     * Dipilih yang menentukan benar-tidaknya isi dokumen di kemudian
     * hari. Data diri yang kosong tidak menimbulkan galat apa pun pada
     * hari pertama; ia baru terlihat berbulan-bulan kemudian, sebagai
     * nama yang hilang di kolom tanda tangan sebuah laporan audit yang
     * sudah dicetak.
     */
    private static function langkahPertama(): array
    {
        /* Tiap tautan dijaga Route::has() lebih dulu.
         *
         * Pengenalan ini digambar dari tata letak, sehingga ia ikut
         * disusun pada SETIAP pembukaan halaman oleh akun yang baru.
         * Satu nama rute yang berganti karenanya tidak berhenti sebagai
         * tautan mati — ia melempar RouteNotFoundException, dan yang
         * dilihat pengguna barunya bukan sambutan melainkan galat 500
         * di seluruh situs, pada hari pertamanya. */
        $calon = [
            ['personalia.index', 'Lengkapi data diri',
             'NRP, jabatan, dan departemen mengisi sendiri kolom pelapor pada Hazard '
             .'Report, inspeksi, dan kop tiap dokumen yang Anda buat.'],

            ['courses.index', 'Mulai dari Learning Center',
             'Prosedur dan SOP yang berlaku di situs Anda ada di sana, beserta kursus '
             .'yang wajib diselesaikan sebelum bekerja di area tertentu.'],

            ['hazard.create', 'Laporkan bahaya yang Anda temui',
             'Hazard Report dapat diisi siapa pun, dari ponsel, langsung di lokasi — '
             .'tidak perlu menunggu kembali ke kantor.'],
        ];

        $butir = [];
        foreach ($calon as [$rute, $judul, $teks]) {
            if (Route::has($rute)) $butir[] = [$judul, $teks, route($rute)];
        }

        return [
            'kunci' => 'mulai',
            'judul' => 'Tiga langkah pertama',
            'teks'  => 'Pengenalan ini dapat dibuka lagi kapan saja lewat menu akun di '
                      .'pojok kanan atas.',
            'butir' => $butir,
        ];
    }
}
