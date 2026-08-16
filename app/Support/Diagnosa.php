<?php

namespace App\Support;

use App\Models\{Certificate, Company, User};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Cache, DB, Schema};

/**
 * Pemeriksaan mandiri sistem.
 *
 * Yang dikumpulkan di sini adalah hal-hal yang, bila salah, TIDAK
 * menimbulkan galat. Aplikasi tetap membuka halamannya, tetap
 * menjawab, tetap terlihat sehat — dan justru itu yang membuatnya
 * berbahaya. APP_DEBUG yang tertinggal menyala hanya terlihat saat ada
 * galat, dan yang melihatnya adalah orang luar. Migrasi yang belum
 * jalan hanya terlihat pada satu halaman yang jarang dibuka.
 * Tautan storage yang putus hanya membuat logo tidak muncul, dan itu
 * dikira urusan tampilan.
 *
 * Karena itu tiap pemeriksaan menyebut TIGA hal, bukan satu:
 * keadaannya, apa akibatnya bila dibiarkan, dan apa langkahnya. Daftar
 * merah tanpa akibat dan tanpa langkah hanya melahirkan kebiasaan
 * mengabaikan warna merah.
 *
 * Yang sengaja TIDAK ada di sini: pemeriksaan yang menebak. Sesuatu
 * yang tidak dapat dijawab dijawab dengan "tidak diketahui", bukan
 * dengan "aman". Pemeriksaan yang salah menyatakan aman lebih buruk
 * daripada tidak ada pemeriksaan sama sekali — ia memindahkan
 * kewaspadaan orang ke tempat yang keliru.
 */
final class Diagnosa
{
    public const AMAN      = 'aman';
    public const PERHATIAN = 'perhatian';
    public const GAWAT     = 'gawat';
    public const TAK_TAHU  = 'tak-tahu';

    /** Urutan keparahan, dipakai mengurutkan hasil. */
    private const BOBOT = [self::GAWAT => 0, self::PERHATIAN => 1, self::TAK_TAHU => 2, self::AMAN => 3];

    /** @var array<string,list<string>>|null peta tabel => kolom, dibaca sekali */
    private static ?array $petaKolom = null;

    /**
     * Jalankan seluruh pemeriksaan.
     *
     * Tiap pemeriksaan dibungkus penangkap galat sendiri: satu
     * pemeriksaan yang meledak — kolom yang belum ada, berkas yang
     * tidak terbaca, sambungan yang putus — tidak boleh mematikan
     * halaman yang justru dibuka untuk mencari tahu apa yang rusak.
     *
     * @return list<array<string,mixed>>
     */
    public static function jalankan(): array
    {
        $hasil = [];

        foreach (self::daftar() as $kode => $periksa) {
            try {
                $hasil[] = ['kode' => $kode] + $periksa();
            } catch (\Throwable $e) {
                $hasil[] = [
                    'kode'     => $kode,
                    'kelompok' => 'Pemeriksaan',
                    'judul'    => $kode,
                    'keadaan'  => self::TAK_TAHU,
                    'nilai'    => 'gagal diperiksa',
                    'uraian'   => 'Pemeriksaannya sendiri gagal berjalan: '.$e->getMessage(),
                    'tindakan' => 'Laporkan pesan di atas — pemeriksaan yang tidak dapat berjalan '
                        .'menyembunyikan keadaan yang seharusnya dilaporkannya.',
                ];
            }
        }

        usort($hasil, fn ($a, $b) => [self::BOBOT[$a['keadaan']], $a['kelompok']]
                                 <=> [self::BOBOT[$b['keadaan']], $b['kelompok']]);

        return $hasil;
    }

    /** Kunci simpanan ringkasan bagi lencana Pusat Kendali. */
    public const KUNCI_RINGKAS = 'diagnosa.ringkas';

    /**
     * Ringkasan yang boleh sedikit basi.
     *
     * Pemeriksaan lengkapnya menyentuh skema setiap tabel — ongkos yang
     * wajar bagi halaman diagnosa, tetapi tidak bagi Pusat Kendali yang
     * hanya menggambar satu lencana. Karena itu lencananya membaca
     * simpanan, dan halaman diagnosanya selalu menghitung ulang.
     *
     * Simpanannya dibuang setiap kali ada tindakan yang dapat
     * mengubah jawabannya — perbaikan, pemeliharaan, pemuatan data
     * contoh. Lencana yang tetap merah sesudah perbaikannya berhasil
     * membuat orang mengulangi perbaikan yang sudah bekerja.
     *
     * @return array<string,int>
     */
    public static function ringkasTersimpan(int $detik = 300): array
    {
        return Cache::remember(self::KUNCI_RINGKAS, $detik,
            fn () => self::ringkas(self::jalankan()));
    }

    /** Simpan ringkasan yang baru saja dihitung, dan buang yang lama. */
    public static function simpanRingkas(array $ringkas, int $detik = 300): void
    {
        Cache::put(self::KUNCI_RINGKAS, $ringkas, $detik);
    }

    public static function lupakanRingkas(): void
    {
        Cache::forget(self::KUNCI_RINGKAS);
    }

    /** @return array<string,int> jumlah per keadaan */
    public static function ringkas(array $hasil): array
    {
        $r = [self::GAWAT => 0, self::PERHATIAN => 0, self::TAK_TAHU => 0, self::AMAN => 0];

        foreach ($hasil as $h) $r[$h['keadaan']]++;

        return $r;
    }

    /** @return array<string,callable():array<string,mixed>> */
    private static function daftar(): array
    {
        return [
            /* ── keamanan ── */
            'debug'          => fn () => self::debug(),
            'kunci-aplikasi' => fn () => self::kunciAplikasi(),
            'skema-url'      => fn () => self::skemaUrl(),
            'kuki-aman'      => fn () => self::kukiAman(),
            'izin-env'       => fn () => self::izinEnv(),
            'bocor-publik'   => fn () => self::bocorPublik(),
            'administrator'  => fn () => self::administrator(),
            'perusahaan-contoh' => fn () => self::perusahaanContoh(),
            'kunci-ai'       => fn () => self::kunciAi(),
            'tekanan-masuk'  => fn () => self::tekananMasuk(),
            'jejak-akses'    => fn () => self::jejakAkses(),
            'umur-sesi'      => fn () => self::umurSesi(),
            'sesi-basi'      => fn () => self::sesiBasi(),

            /* ── basis data ── */
            'migrasi'        => fn () => self::migrasi(),

            /* ── berkas & disk ── */
            'ruang-disk'     => fn () => self::ruangDisk(),
            'dapat-ditulis'  => fn () => self::dapatDitulis(),
            'tautan-storage' => fn () => self::tautanStorage(),
            'berkas-bangun'  => fn () => self::berkasBangun(),
            'cache-siap'     => fn () => self::cacheSiap(),

            /* ── antrean & log ── */
            'antrean-gagal'  => fn () => self::antreanGagal(),
            'antrean-tumpuk' => fn () => self::antreanTumpuk(),
            'log-galat'      => fn () => self::logGalat(),
            'log-besar'      => fn () => self::logBesar(),

            /* ── keutuhan data ── */
            'nomor-kembar'   => fn () => self::nomorKembar(),
            'tanpa-pemilik'  => fn () => self::tanpaPemilik(),
            'pengguna-yatim' => fn () => self::penggunaYatim(),
            'tertahan'       => fn () => self::tertahan(),
        ];
    }

    /* ═══════════════ keamanan ═══════════════ */

    private static function debug(): array
    {
        $nyala = (bool) config('app.debug');
        $produksi = app()->environment('production');

        return [
            'kelompok' => 'Keamanan',
            'judul'    => 'Mode debug',
            'keadaan'  => $nyala && $produksi ? self::GAWAT : ($nyala ? self::PERHATIAN : self::AMAN),
            'nilai'    => $nyala ? 'AKTIF' : 'nonaktif',
            'uraian'   => $nyala
                ? 'Halaman galat menampilkan isi berkas .env kepada siapa pun yang memicunya — '
                  .'termasuk sandi basis data, kunci aplikasi, dan kunci API. Satu URL yang salah '
                  .'ketik sudah cukup untuk memunculkannya, dan tidak ada jejak siapa yang melihat.'
                : 'Halaman galat tidak membocorkan isi konfigurasi.',
            'tindakan' => $nyala
                ? 'Setel APP_DEBUG=false pada .env di server, lalu bersihkan cache konfigurasi.'
                : null,
        ];
    }

    private static function kunciAplikasi(): array
    {
        $ada = (bool) config('app.key');

        return [
            'kelompok' => 'Keamanan',
            'judul'    => 'Kunci aplikasi',
            'keadaan'  => $ada ? self::AMAN : self::GAWAT,
            'nilai'    => $ada ? 'terpasang' : 'KOSONG',
            'uraian'   => $ada
                ? 'Kuki sesi dan data terenkripsi memakai kunci ini.'
                : 'Tanpa APP_KEY, kuki sesi tidak terenkripsi dan seluruh nilai terenkripsi '
                  .'tidak dapat dibaca kembali. Sesi pengguna dapat dipalsukan.',
            'tindakan' => $ada ? null : 'Jalankan php artisan key:generate di server.',
        ];
    }

    private static function skemaUrl(): array
    {
        $url = (string) config('app.url');
        $https = str_starts_with($url, 'https://');
        $produksi = app()->environment('production');

        /* Di luar produksi, http memang yang sepatutnya: pengembangan
           lokal tidak punya sertifikat, dan menuntutnya di sana hanya
           menghasilkan baris merah yang benar untuk diabaikan — dan
           kebiasaan mengabaikan itu yang kemudian terbawa ke produksi. */
        $bermasalah = !$https && $produksi;

        return [
            'kelompok' => 'Keamanan',
            'judul'    => 'Skema APP_URL',
            'keadaan'  => $bermasalah ? self::PERHATIAN : self::AMAN,
            'nilai'    => $url ?: '—',
            'uraian'   => $https
                ? 'Tautan yang dibuat di latar — verifikasi surel, pengalihan bertandatangan, '
                  .'alamat gambar pada berkas cetak — memakai https.'
                : ($produksi
                    ? 'Tautan yang dibuat di latar memakai http. Tautan verifikasi surel dan '
                      .'pengalihan bertandatangan karena itu lahir sebagai http, dan sebagian '
                      .'peramban menolak atau menurunkan mutunya diam-diam.'
                    : 'http memang yang sepatutnya di lingkungan '.app()->environment().'.'),
            'tindakan' => $bermasalah
                ? 'Setel APP_URL ke alamat https yang sebenarnya, lalu bersihkan cache konfigurasi.'
                : null,
        ];
    }

    private static function kukiAman(): array
    {
        $https = str_starts_with((string) config('app.url'), 'https://');
        $aman  = config('session.secure');

        /* Hanya berarti bila situsnya memang https. Pada http, kuki
           bertanda secure justru TIDAK PERNAH terkirim, dan akibatnya
           tidak ada yang bisa masuk sama sekali. */
        if (!$https) {
            return [
                'kelompok' => 'Keamanan',
                'judul'    => 'Kuki sesi aman',
                'keadaan'  => self::AMAN,
                'nilai'    => 'tidak berlaku (situs http)',
                'uraian'   => 'Penandaan secure hanya berarti pada situs https.',
                'tindakan' => null,
            ];
        }

        return [
            'kelompok' => 'Keamanan',
            'judul'    => 'Kuki sesi aman',
            'keadaan'  => $aman ? self::AMAN : self::PERHATIAN,
            'nilai'    => $aman ? 'secure + httpOnly' : 'tanpa secure',
            'uraian'   => $aman
                ? 'Kuki sesi hanya dikirim lewat https dan tidak terbaca oleh skrip halaman.'
                : 'Kuki sesi masih dikirim pada sambungan http. Di jaringan bersama — Wi-Fi '
                  .'mes, jaringan tamu di kantor site — sesi yang lewat dapat direkam dan dipakai '
                  .'ulang tanpa perlu tahu sandinya.',
            'tindakan' => $aman ? null : 'Setel SESSION_SECURE_COOKIE=true pada .env di server.',
        ];
    }

    private static function izinEnv(): array
    {
        $berkas = base_path('.env');

        if (!is_file($berkas)) {
            return [
                'kelompok' => 'Keamanan',
                'judul'    => 'Izin berkas .env',
                'keadaan'  => self::TAK_TAHU,
                'nilai'    => 'tidak ditemukan',
                'uraian'   => 'Berkas .env tidak ada di tempat yang diharapkan.',
                'tindakan' => 'Periksa apakah konfigurasi dipasok lewat variabel lingkungan.',
            ];
        }

        $mode = fileperms($berkas) & 0777;
        $lain = $mode & 0007;      // hak bagi "other"

        return [
            'kelompok' => 'Keamanan',
            'judul'    => 'Izin berkas .env',
            'keadaan'  => $lain ? self::GAWAT : ($mode & 0070 ? self::PERHATIAN : self::AMAN),
            'nilai'    => '0'.decoct($mode),
            'uraian'   => $lain
                ? 'Berkas .env dapat dibaca oleh SETIAP pengguna di server ini, termasuk akun '
                  .'layanan lain yang berbagi mesin. Isinya sandi basis data dan kunci aplikasi.'
                : ($mode & 0070
                    ? 'Berkas .env dapat dibaca oleh grup. Cukup bila hanya proses web yang '
                      .'berada di grup itu; berbahaya bila grupnya dipakai bersama.'
                    : 'Hanya pemiliknya yang dapat membaca .env.'),
            'tindakan' => $lain || ($mode & 0070)
                ? 'Jalankan chmod 600 .env di server (atau 640 bila proses web berjalan sebagai grup terpisah).'
                : null,
        ];
    }

    /**
     * Berkas yang tidak sepatutnya berada di dalam direktori publik.
     *
     * Semua yang ada di public/ dapat diunduh siapa pun tanpa masuk.
     * Salinan .env yang tertinggal di situ, atau berkas basis data yang
     * pernah disalin ke sana "sebentar saja", membocorkan seluruh isi
     * sistem dalam satu permintaan — tanpa galat, tanpa jejak di log
     * aplikasi, dan tanpa satu pun peringatan.
     */
    private static function bocorPublik(): array
    {
        $bahaya = [];

        foreach (['.env', '.env.backup', '.env.example.local', '.git', 'storage/app',
                  'database.sqlite', 'database', 'composer.json', '.htpasswd'] as $nama) {
            $jalan = public_path($nama);

            if (file_exists($jalan)) $bahaya[] = $nama;
        }

        foreach (glob(public_path('*.sql')) ?: [] as $j) $bahaya[] = basename($j);
        foreach (glob(public_path('*.sqlite*')) ?: [] as $j) $bahaya[] = basename($j);

        $bahaya = array_values(array_unique($bahaya));

        return [
            'kelompok' => 'Keamanan',
            'judul'    => 'Berkas terbuka di direktori publik',
            'keadaan'  => $bahaya ? self::GAWAT : self::AMAN,
            'nilai'    => $bahaya ? implode(', ', $bahaya) : 'bersih',
            'uraian'   => $bahaya
                ? 'Berkas di atas berada di dalam public/ dan dapat diunduh siapa pun tanpa masuk, '
                  .'cukup dengan menebak namanya. Salinan basis data dan .env yang tertinggal di '
                  .'sini membocorkan seluruh isi sistem dalam satu permintaan.'
                : 'Tidak ada berkas rahasia atau salinan basis data di dalam public/.',
            'tindakan' => $bahaya
                ? 'Pindahkan atau hapus berkas tersebut dari public/. Bila itu salinan basis data '
                  .'atau .env, anggap isinya sudah bocor: ganti sandi basis data dan APP_KEY.'
                : null,
        ];
    }

    private static function administrator(): array
    {
        $n = User::query()->where('is_admin', true)->count();
        $aktif = User::query()->where('is_admin', true)->where('active', true)->count();

        return [
            'kelompok' => 'Keamanan',
            'judul'    => 'Jumlah administrator',
            'keadaan'  => $n === 0 ? self::GAWAT : ($n > 5 ? self::PERHATIAN : self::AMAN),
            'nilai'    => $n.' akun'.($aktif !== $n ? " ({$aktif} aktif)" : ''),
            'uraian'   => $n === 0
                ? 'Tidak ada administrator sama sekali. Pengaturan sistem, pengguna, dan '
                  .'perusahaan tidak dapat diubah oleh siapa pun.'
                : 'Administrator melewati SELURUH batas perusahaan: ia melihat dan mengubah data '
                  .'setiap perusahaan di sistem ini, termasuk yang bukan urusannya. Hak itu tidak '
                  .'meninggalkan jejak khusus di log.',
            'tindakan' => $n > 5
                ? 'Tinjau daftarnya; yang hanya perlu mengelola satu perusahaan cukup diberi '
                  .'peran di perusahaan itu, bukan hak administrator.'
                : ($n === 0 ? 'Tetapkan satu akun sebagai administrator lewat konsol.' : null),
        ];
    }

    private static function perusahaanContoh(): array
    {
        if (!Schema::hasColumn('companies', 'demo')) {
            return self::lewat('Keamanan', 'Perusahaan contoh', 'kolom belum ada');
        }

        $nama = Company::query()->where('demo', true)->pluck('name')->all();

        return [
            'kelompok' => 'Keamanan',
            'judul'    => 'Perusahaan contoh',
            'keadaan'  => $nama ? self::PERHATIAN : self::AMAN,
            'nilai'    => $nama ? implode(', ', $nama) : 'tidak ada',
            'uraian'   => $nama
                ? 'Perusahaan di atas dapat dikosongkan dan diisi ulang oleh satu tombol di Pusat '
                  .'Kendali. Itu memang gunanya — tetapi hanya selama perusahaan itu benar-benar '
                  .'perusahaan uji, bukan perusahaan yang sudah dipakai bekerja.'
                : 'Tidak ada perusahaan yang datanya dapat dibuang oleh pemuat data contoh.',
            'tindakan' => $nama
                ? 'Bila salah satunya sudah dipakai bekerja, lepas tandanya di Pusat Kendali sekarang.'
                : null,
        ];
    }

    /**
     * Kunci AI yang tersimpan tetapi tidak dapat dibaca.
     *
     * Kuncinya dienkripsi dengan APP_KEY. Bila APP_KEY berganti — dipulihkan
     * dari cadangan, atau dibuat ulang karena dikira hilang — kuncinya masih
     * ada di basis data tetapi tidak lagi dapat dibuka. Asistennya lalu diam
     * seolah belum pernah diatur, sementara halaman pengaturannya
     * memperlihatkan sesuatu yang tersimpan. Dua keterangan yang saling
     * membantah, dan tidak satu pun galat yang menengahi.
     */
    /**
     * Tekanan pada pintu masuk dalam satu jam terakhir.
     *
     * Yang diukur percobaan yang GAGAL, bukan yang berhasil. Serangan
     * penebakan sandi hampir seluruhnya berupa kegagalan, dan tepat
     * karena itu ia tidak pernah muncul di mana pun: tidak menimbulkan
     * galat, tidak mengisi log, tidak mengganggu siapa pun — sampai
     * ada satu yang berhasil, dan sesudah itu jejaknya menjadi jejak
     * pengguna yang sah.
     */
    private static function tekananMasuk(): array
    {
        if (!Schema::hasTable('activity_log')) {
            return self::lewat('Keamanan', 'Tekanan pintu masuk', 'tabel jejak belum ada');
        }

        $sejam   = Keamanan::gagalSejak(1);
        $sehari  = Keamanan::gagalSejak(24);
        $keadaan = Keamanan::keadaanTekanan($sejam);

        return [
            'kelompok' => 'Keamanan',
            'judul'    => 'Tekanan pintu masuk',
            'keadaan'  => $keadaan,
            'nilai'    => $sejam.' gagal / jam · '.$sehari.' / hari',
            'uraian'   => $keadaan === self::AMAN
                ? 'Percobaan masuk yang gagal masih pada tingkat yang wajar untuk salah ketik.'
                : 'Percobaan masuk yang gagal jauh di atas kewajaran. Batas bawaan adalah lima '
                  .'percobaan per surel per alamat, jadi angka ini berarti beberapa penguncian '
                  .'beruntun — pola yang tidak dihasilkan orang yang sekadar lupa sandinya.',
            'tindakan' => $keadaan === self::AMAN ? null
                : 'Buka Keamanan & Jaringan untuk melihat alamat mana yang menekan dan surel '
                  .'siapa yang disasar. Satu alamat yang mencoba banyak surel adalah pemindaian; '
                  .'satu alamat pada satu surel adalah penebakan sandi orang itu — hubungi '
                  .'pemilik akunnya.',
        ];
    }

    /**
     * Apakah jejak akses benar-benar terisi.
     *
     * Pemeriksaan ini menjaga pencatatnya sendiri. Pencatat yang mati —
     * listener yang tidak terdaftar sesudah penyusunan ulang provider,
     * kolom yang hilang sesudah migrasi mundur — tidak menimbulkan
     * galat apa pun; halamannya tetap terbuka dan tabelnya tetap ada,
     * hanya kosong. Dan jejak yang kosong terlihat persis seperti
     * keadaan aman.
     */
    private static function jejakAkses(): array
    {
        if (!Schema::hasTable('activity_log')
            || !in_array('ip', self::petaKolom()['activity_log'] ?? [], true)) {
            return [
                'kelompok' => 'Keamanan',
                'judul'    => 'Jejak akses',
                'keadaan'  => self::GAWAT,
                'nilai'    => 'kolom alamat belum ada',
                'uraian'   => 'Jejak aktivitas berjalan tanpa alamat dan tanpa perangkat. '
                    .'Pertanyaan "apakah ini benar orangnya" tidak akan dapat dijawab.',
                'tindakan' => 'Jalankan php artisan migrate di server.',
            ];
        }

        $adaPengguna = Schema::hasTable('users') && User::query()->count() > 0;

        $terakhir = DB::table('activity_log')
            ->where('module', Keamanan::MODUL)
            ->max('created_at');

        /* Pemasangan yang baru berdiri belum punya jejak, dan itu bukan
           kesalahan. Yang mencurigakan adalah pemasangan yang sudah
           punya pengguna tetapi tidak punya satu pun peristiwa masuk. */
        if (!$terakhir) {
            return [
                'kelompok' => 'Keamanan',
                'judul'    => 'Jejak akses',
                'keadaan'  => $adaPengguna ? self::PERHATIAN : self::AMAN,
                'nilai'    => 'belum ada peristiwa',
                'uraian'   => $adaPengguna
                    ? 'Sudah ada pengguna, tetapi belum satu pun peristiwa masuk tercatat. '
                      .'Bila ada yang sudah pernah masuk sesudah pembaruan ini, pencatatnya '
                      .'tidak berjalan.'
                    : 'Belum ada yang pernah masuk. Wajar pada pemasangan yang baru berdiri.',
                'tindakan' => $adaPengguna
                    ? 'Keluar lalu masuk kembali, dan periksa halaman Keamanan & Jaringan. '
                      .'Bila tetap kosong, bersihkan cache konfigurasi: php artisan config:clear.'
                    : null,
            ];
        }

        return [
            'kelompok' => 'Keamanan',
            'judul'    => 'Jejak akses',
            'keadaan'  => self::AMAN,
            'nilai'    => 'tercatat, terakhir '.Carbon::parse($terakhir)->diffForHumans(),
            'uraian'   => 'Setiap percobaan masuk — berhasil maupun gagal — tercatat beserta '
                .'alamat dan perangkatnya. Sandi tidak pernah ikut tercatat.',
            'tindakan' => null,
        ];
    }

    /**
     * Masa berlaku sesi.
     *
     * Terlalu panjang berarti perangkat yang tertinggal di kantor site
     * tetap terbuka berhari-hari. Terlalu pendek membuat orang keluar
     * sendiri di tengah pengisian borang lapangan, dan itu berakhir
     * pada permintaan untuk memanjangkannya lagi tanpa batas.
     */
    private static function umurSesi(): array
    {
        $menit = (int) config('session.lifetime', 120);
        $jam   = round($menit / 60, 1);

        $panjang = $menit > 60 * 24 * 7;    // lebih dari sepekan

        return [
            'kelompok' => 'Keamanan',
            'judul'    => 'Masa berlaku sesi',
            'keadaan'  => $panjang ? self::PERHATIAN : self::AMAN,
            'nilai'    => $menit.' menit ('.$jam.' jam)',
            'uraian'   => $panjang
                ? 'Sesi bertahan lebih dari sepekan tanpa aktivitas. Perangkat bersama di kantor '
                  .'site — komputer ruang rapat, tablet pengawas shift — tetap dapat membuka '
                  .'akun orang yang sudah pulang berhari-hari lalu.'
                : 'Sesi berakhir sendiri setelah tidak dipakai selama masa itu.',
            'tindakan' => $panjang
                ? 'Turunkan SESSION_LIFETIME pada .env. Pertimbangkan 480 (satu shift) bila '
                  .'alasan memanjangkannya adalah pengisian borang lapangan yang lama.'
                : null,
        ];
    }

    /**
     * Baris sesi yang masa berlakunya sudah lewat tetapi belum dibuang.
     *
     * Tidak berbahaya dengan sendirinya — pembacanya menolak sesi
     * kedaluwarsa — tetapi tiap baris menyimpan alamat dan perangkat
     * seseorang. Menyimpan data yang tidak dipakai lagi hanya menambah
     * yang dapat hilang tanpa menambah yang dapat dikerjakan.
     */
    private static function sesiBasi(): array
    {
        /* Bukan "tidak diketahui" melainkan "tidak berlaku". Penyimpan
           sesi selain basis data tidak meninggalkan baris yang
           menumpuk, jadi tidak ada yang tidak terjawab di sini —
           pertanyaannya yang memang tidak ada. Menyebutnya tak-tahu
           akan menaruh tanda tanya permanen pada daftar diagnosa, dan
           tanda tanya yang tidak pernah dapat dijawab mengajari orang
           mengabaikan tanda tanya berikutnya. */
        if (config('session.driver') !== 'database' || !Schema::hasTable('sessions')) {
            return [
                'kelompok' => 'Keamanan',
                'judul'    => 'Sesi kedaluwarsa',
                'keadaan'  => self::AMAN,
                'nilai'    => 'tidak berlaku (sesi disimpan '.config('session.driver').')',
                'uraian'   => 'Penyimpan sesi ini tidak meninggalkan baris kedaluwarsa yang '
                    .'menumpuk. Daftar perangkat aktif juga tidak tersedia karenanya.',
                'tindakan' => null,
            ];
        }

        $basi  = Keamanan::sesiBasi();
        $aktif = Keamanan::jumlahSesiAktif();

        return [
            'kelompok' => 'Keamanan',
            'judul'    => 'Sesi kedaluwarsa',
            'keadaan'  => $basi > 500 ? self::PERHATIAN : self::AMAN,
            'nilai'    => $basi.' basi · '.$aktif.' aktif',
            'uraian'   => $basi > 500
                ? 'Ribuan baris sesi mati masih tersimpan, masing-masing memuat alamat dan '
                  .'perangkat seseorang. Tidak dapat dipakai untuk masuk, tetapi tetap data '
                  .'pribadi yang disimpan tanpa keperluan.'
                : 'Jumlah sesi mati yang tersimpan masih wajar.',
            'tindakan' => $basi > 500
                ? 'Tekan "Bersihkan sesi kedaluwarsa" pada halaman Keamanan & Jaringan.'
                : null,
        ];
    }

    private static function kunciAi(): array
    {
        if (!Schema::hasTable('app_settings')) {
            return self::lewat('Keamanan', 'Kunci AI', 'tabel pengaturan belum ada');
        }

        $tersimpan = DB::table('app_settings')->where('key', 'like', 'ai_kunci_%')->pluck('key');
        $rusak = [];

        foreach ($tersimpan as $k) {
            $penyedia = str_replace('ai_kunci_', '', $k);

            if (!Ai::punyaKunci($penyedia)) $rusak[] = $penyedia;
        }

        if ($rusak) {
            return [
                'kelompok' => 'Keamanan',
                'judul'    => 'Kunci AI',
                'keadaan'  => self::PERHATIAN,
                'nilai'    => 'tidak terbaca: '.implode(', ', $rusak),
                'uraian'   => 'Kunci tersimpan tetapi tidak dapat didekripsi — hampir selalu berarti '
                    .'APP_KEY berganti sesudah kuncinya disimpan. Asistennya diam seolah belum '
                    .'pernah diatur, sementara halaman pengaturannya memperlihatkan sesuatu yang ada.',
                'tindakan' => 'Masukkan ulang kunci API di halaman Integrasi AI; yang lama tidak '
                    .'dapat dipulihkan dan memang tidak perlu.',
            ];
        }

        return [
            'kelompok' => 'Keamanan',
            'judul'    => 'Kunci AI',
            'keadaan'  => self::AMAN,
            'nilai'    => Ai::aktif()
                ? AiPenyedia::satu(Ai::penyedia())['nama'].' · '.Ai::model()
                : 'belum diaktifkan',
            'uraian'   => Ai::aktif()
                ? 'Asisten AI aktif, dan kuncinya tersimpan terenkripsi.'
                : 'Asisten AI mati. Itu keadaan yang sah — tanpa kunci, pertanyaan diteruskan '
                  .'ke admin alih-alih dijawab dengan tebakan.',
            'tindakan' => null,
        ];
    }

    /* ═══════════════ basis data ═══════════════ */

    private static function migrasi(): array
    {
        if (!Schema::hasTable('migrations')) {
            return [
                'kelompok' => 'Basis data',
                'judul'    => 'Migrasi',
                'keadaan'  => self::GAWAT,
                'nilai'    => 'belum pernah dijalankan',
                'uraian'   => 'Tabel migrations belum ada; basis data ini belum disiapkan.',
                'tindakan' => 'Jalankan php artisan migrate --force di server.',
            ];
        }

        $sudah = DB::table('migrations')->pluck('migration')->flip();
        $tunggak = [];

        foreach (glob(database_path('migrations/*.php')) ?: [] as $berkas) {
            $nama = basename($berkas, '.php');

            if (!$sudah->has($nama)) $tunggak[] = $nama;
        }

        return [
            'kelompok' => 'Basis data',
            'judul'    => 'Migrasi tertunda',
            'keadaan'  => $tunggak ? self::GAWAT : self::AMAN,
            'nilai'    => $tunggak ? count($tunggak).' tertunda' : 'mutakhir',
            'uraian'   => $tunggak
                ? 'Kode yang berjalan menyebut kolom dan tabel yang belum ada. Halaman yang '
                  .'menyentuhnya akan gagal — dan gagalnya hanya pada halaman itu, sehingga '
                  .'sisanya tetap terlihat normal. Tertunda: '.implode(', ', array_slice($tunggak, 0, 5))
                  .(count($tunggak) > 5 ? ' dan '.(count($tunggak) - 5).' lagi' : '')
                : 'Skema basis data sudah selaras dengan kode yang berjalan.',
            'tindakan' => $tunggak ? 'Jalankan migrasi dari tombol perbaikan di bawah, atau '
                .'php artisan migrate --force di server.' : null,
        ];
    }

    /* ═══════════════ berkas & disk ═══════════════ */

    private static function ruangDisk(): array
    {
        $sisa  = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());

        if (!$sisa || !$total) return self::lewat('Berkas & disk', 'Ruang disk', 'tidak terbaca');

        $persen = $sisa / $total * 100;

        return [
            'kelompok' => 'Berkas & disk',
            'judul'    => 'Ruang disk',
            'keadaan'  => $persen < 5 ? self::GAWAT : ($persen < 15 ? self::PERHATIAN : self::AMAN),
            'nilai'    => self::ukuran($sisa).' sisa dari '.self::ukuran($total)
                          .' ('.number_format($persen, 1, ',', '.').'%)',
            'uraian'   => $persen < 15
                ? 'Disk yang penuh membuat penyimpanan gagal DI TENGAH pekerjaan: unggahan '
                  .'terpotong, log berhenti tertulis, dan basis data SQLite dapat menolak '
                  .'transaksi. Kegagalannya tidak seragam, sehingga sulit dikenali sebagai '
                  .'satu sebab yang sama.'
                : 'Ruang disk masih longgar.',
            'tindakan' => $persen < 15
                ? 'Potong log lama, buang cache lama, dan periksa storage/app/public — '
                  .'unggahan gambar biasanya penyumbang terbesar.'
                : null,
        ];
    }

    private static function dapatDitulis(): array
    {
        $jalur = [
            'storage/app'              => storage_path('app'),
            'storage/framework/cache'  => storage_path('framework/cache'),
            'storage/framework/views'  => storage_path('framework/views'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/logs'             => storage_path('logs'),
            'bootstrap/cache'          => base_path('bootstrap/cache'),
        ];

        $gagal = [];
        foreach ($jalur as $label => $j) {
            if (!is_dir($j) || !is_writable($j)) $gagal[] = $label;
        }

        return [
            'kelompok' => 'Berkas & disk',
            'judul'    => 'Direktori yang harus dapat ditulis',
            'keadaan'  => $gagal ? self::GAWAT : self::AMAN,
            'nilai'    => $gagal ? implode(', ', $gagal) : count($jalur).' direktori siap',
            'uraian'   => $gagal
                ? 'Direktori di atas tidak dapat ditulis oleh proses web. Akibatnya berbeda-beda '
                  .'dan tidak satu pun menyebut sebabnya: unggahan hilang tanpa pesan, sesi '
                  .'tidak tersimpan sehingga pengguna terus terlempar keluar, dan log berhenti '
                  .'mencatat tepat ketika ia paling dibutuhkan.'
                : 'Seluruh direktori kerja dapat ditulis.',
            'tindakan' => $gagal
                ? 'Jalankan chown -R www-data:www-data storage bootstrap/cache di server '
                  .'(sesuaikan nama penggunanya dengan yang menjalankan PHP-FPM).'
                : null,
        ];
    }

    private static function tautanStorage(): array
    {
        $tautan = public_path('storage');
        $ada = file_exists($tautan);
        $benar = $ada && (!is_link($tautan) || realpath(readlink($tautan)) === realpath(storage_path('app/public')));

        return [
            'kelompok' => 'Berkas & disk',
            'judul'    => 'Tautan storage publik',
            'keadaan'  => $ada && $benar ? self::AMAN : self::PERHATIAN,
            'nilai'    => !$ada ? 'tidak ada' : ($benar ? 'terpasang' : 'menunjuk ke tempat lain'),
            'uraian'   => $ada && $benar
                ? 'Berkas unggahan dapat diakses dari peramban.'
                : 'Tanpa tautan ini, seluruh berkas unggahan tidak muncul: logo perusahaan, '
                  .'gambar tanda tangan pada sertifikat, foto barang gudang, dan lampiran '
                  .'dokumen. Halamannya tetap terbuka, hanya gambarnya kosong — sehingga '
                  .'terbaca sebagai urusan tampilan, bukan tautan yang putus.',
            'tindakan' => $ada && $benar ? null
                : 'Tekan tombol "Pasang tautan storage" di bawah, atau jalankan '
                  .'php artisan storage:link di server.',
        ];
    }

    private static function berkasBangun(): array
    {
        $manifes = public_path('build/manifest.json');
        $ada = is_file($manifes);

        return [
            'kelompok' => 'Berkas & disk',
            'judul'    => 'Hasil bangun antarmuka',
            'keadaan'  => $ada ? self::AMAN : self::GAWAT,
            'nilai'    => $ada ? 'ada · '.date('d M Y H:i', filemtime($manifes)) : 'TIDAK ADA',
            'uraian'   => $ada
                ? 'Berkas JS dan CSS hasil bangun tersedia.'
                : 'Tanpa manifest.json, setiap halaman gagal memuat skrip dan tampil kosong '
                  .'atau putih. Ini yang terjadi bila npm run build terlewat saat menerbitkan.',
            'tindakan' => $ada ? null : 'Jalankan npm ci && npm run build di server.',
        ];
    }

    private static function cacheSiap(): array
    {
        $config = is_file(base_path('bootstrap/cache/config.php'));
        $rute   = is_file(base_path('bootstrap/cache/routes-v7.php'))
               || is_file(base_path('bootstrap/cache/routes.php'));
        $produksi = app()->environment('production');

        if ($produksi) {
            $kurang = array_keys(array_filter(['konfigurasi' => !$config, 'rute' => !$rute]));

            return [
                'kelompok' => 'Berkas & disk',
                'judul'    => 'Cache konfigurasi dan rute',
                'keadaan'  => $kurang ? self::PERHATIAN : self::AMAN,
                'nilai'    => $kurang ? 'belum di-cache: '.implode(', ', $kurang) : 'terpasang',
                'uraian'   => $kurang
                    ? 'Tanpa cache, setiap permintaan membaca ulang seluruh berkas konfigurasi '
                      .'dan mendaftar ulang seluruh rute. Sistemnya tetap benar, hanya lebih '
                      .'lambat — dan lambatnya merata sehingga tidak menunjuk pada satu sebab.'
                    : 'Konfigurasi dan rute sudah di-cache.',
                'tindakan' => $kurang ? 'Tekan "Bangun cache produksi" di bawah.' : null,
            ];
        }

        return [
            'kelompok' => 'Berkas & disk',
            'judul'    => 'Cache konfigurasi dan rute',
            'keadaan'  => $config ? self::PERHATIAN : self::AMAN,
            'nilai'    => $config ? 'ADA di lingkungan '.app()->environment() : 'tidak ada (semestinya)',
            'uraian'   => $config
                ? 'Di luar produksi, cache konfigurasi membuat perubahan .env TIDAK terbaca. '
                  .'Nilai lama terus dipakai tanpa satu pun tanda, dan waktu terbuang mencari '
                  .'sebab pada tempat yang salah.'
                : 'Konfigurasi dibaca langsung, sehingga perubahan .env langsung berlaku.',
            'tindakan' => $config ? 'Tekan "Bersihkan semua cache".' : null,
        ];
    }

    /* ═══════════════ antrean & log ═══════════════ */

    private static function antreanGagal(): array
    {
        if (!Schema::hasTable('failed_jobs')) {
            return self::lewat('Antrean & log', 'Pekerjaan gagal', 'tabel tidak ada');
        }

        $n = DB::table('failed_jobs')->count();
        $terakhir = $n ? DB::table('failed_jobs')->max('failed_at') : null;

        return [
            'kelompok' => 'Antrean & log',
            'judul'    => 'Pekerjaan antrean yang gagal',
            'keadaan'  => $n === 0 ? self::AMAN : ($n > 20 ? self::GAWAT : self::PERHATIAN),
            'nilai'    => $n.($terakhir ? ' · terakhir '.$terakhir : ''),
            'uraian'   => $n
                ? 'Pekerjaan yang gagal berhenti diam-diam: surel yang tidak pernah terkirim, '
                  .'berkas yang tidak pernah selesai diproses. Pemanggilnya sudah menerima '
                  .'jawaban "berhasil" jauh sebelum kegagalannya terjadi.'
                : 'Tidak ada pekerjaan antrean yang gagal.',
            'tindakan' => $n ? 'Periksa penyebabnya, lalu tekan "Coba ulang antrean gagal".' : null,
        ];
    }

    private static function antreanTumpuk(): array
    {
        if (!Schema::hasTable('jobs')) {
            return self::lewat('Antrean & log', 'Antrean menumpuk', 'tabel tidak ada');
        }

        $n = DB::table('jobs')->count();
        $tertua = $n ? DB::table('jobs')->min('available_at') : null;
        $umurJam = $tertua ? (int) floor((time() - (int) $tertua) / 3600) : 0;

        return [
            'kelompok' => 'Antrean & log',
            'judul'    => 'Antrean menunggu',
            'keadaan'  => $umurJam >= 6 ? self::GAWAT : ($n > 100 || $umurJam >= 1 ? self::PERHATIAN : self::AMAN),
            'nilai'    => $n.' menunggu'.($umurJam ? " · tertua {$umurJam} jam" : ''),
            'uraian'   => $umurJam >= 1
                ? 'Pekerjaan tertua sudah menunggu berjam-jam. Itu bukan antrean yang panjang, '
                  .'melainkan pekerja antrean yang tidak berjalan — dan selama ia mati, semua '
                  .'yang ditunda tidak pernah terjadi meski tidak satu pun tercatat gagal.'
                : 'Antrean bergerak.',
            'tindakan' => $umurJam >= 1
                ? 'Periksa proses queue:work di server (systemd atau supervisor); pastikan ia '
                  .'berjalan dan dimulai ulang setelah penerbitan.'
                : null,
        ];
    }

    private static function logGalat(): array
    {
        $berkas = storage_path('logs/laravel.log');

        if (!is_file($berkas)) return self::lewat('Antrean & log', 'Galat di log', 'log belum ada');

        /* Hanya bagian AKHIR berkas yang dibaca. Log produksi dapat
           berukuran ratusan megabita, dan membacanya seluruhnya untuk
           menghitung baris justru dapat menghabiskan memori pada
           halaman yang dibuka orang karena sistemnya sedang bermasalah. */
        $ekor = self::ekorBerkas($berkas, 512 * 1024);

        $hariIni = now()->format('Y-m-d');
        $kemarin = now()->subDay()->format('Y-m-d');

        $n = preg_match_all(
            '/^\['.preg_quote($hariIni, '/').'|^\['.preg_quote($kemarin, '/').'/mi',
            $ekor, $c1);
        $berat = preg_match_all('/\.(ERROR|CRITICAL|ALERT|EMERGENCY):/', $ekor);

        return [
            'kelompok' => 'Antrean & log',
            'judul'    => 'Galat di log',
            'keadaan'  => $berat === 0 ? self::AMAN : ($berat > 50 ? self::GAWAT : self::PERHATIAN),
            'nilai'    => $berat.' galat berat pada 512 KB terakhir'
                          .($n ? " · {$n} baris dalam 2 hari terakhir" : ''),
            'uraian'   => $berat
                ? 'Galat yang tercatat tetapi tidak pernah dibaca sama saja dengan galat yang '
                  .'tidak tercatat. Sebagian besar kegagalan di sistem ini berakhir sebagai '
                  .'satu baris di sini, bukan sebagai halaman merah yang dilihat orang.'
                : 'Tidak ada galat berat pada bagian akhir log.',
            'tindakan' => $berat ? 'Buka storage/logs/laravel.log di server dan telusuri dari '
                .'baris terbaru ke belakang.' : null,
        ];
    }

    private static function logBesar(): array
    {
        $berkas = storage_path('logs/laravel.log');

        if (!is_file($berkas)) return self::lewat('Antrean & log', 'Ukuran log', 'log belum ada');

        $b = filesize($berkas);

        return [
            'kelompok' => 'Antrean & log',
            'judul'    => 'Ukuran berkas log',
            'keadaan'  => $b > 200 * 1024 * 1024 ? self::GAWAT
                        : ($b > 50 * 1024 * 1024 ? self::PERHATIAN : self::AMAN),
            'nilai'    => self::ukuran($b),
            'uraian'   => $b > 50 * 1024 * 1024
                ? 'Log yang membesar tanpa batas memakan disk yang sama dengan basis data, dan '
                  .'ketika disk penuh yang gagal lebih dulu adalah penyimpanan data, bukan '
                  .'penulisan lognya.'
                : 'Ukuran log wajar.',
            'tindakan' => $b > 50 * 1024 * 1024
                ? 'Tekan "Potong berkas log" di bawah, dan pasang logrotate di server agar '
                  .'tidak berulang.'
                : null,
        ];
    }

    /* ═══════════════ keutuhan data ═══════════════ */

    /**
     * Nomor sertifikat yang terpakai lebih dari sekali.
     *
     * Peninggalan penghitung lama yang memakai count()+1: satu
     * sertifikat yang dihapus membuat nomor berikutnya mengulang nomor
     * yang sudah pernah terbit. Dua lembar bernomor sama, keduanya
     * lolos verifikasi barcode, dan tidak ada cara membedakan mana yang
     * asli dari nomornya saja.
     */
    private static function nomorKembar(): array
    {
        if (!Schema::hasTable('certificates')) {
            return self::lewat('Keutuhan data', 'Nomor sertifikat kembar', 'tabel tidak ada');
        }

        $kembar = Certificate::query()->withoutGlobalScopes()
            ->select('certificate_number', DB::raw('COUNT(*) as n'))
            ->whereNotNull('certificate_number')
            ->groupBy('certificate_number')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('n', 'certificate_number');

        return [
            'kelompok' => 'Keutuhan data',
            'judul'    => 'Nomor sertifikat kembar',
            'keadaan'  => $kembar->isEmpty() ? self::AMAN : self::GAWAT,
            'nilai'    => $kembar->isEmpty() ? 'tidak ada'
                : $kembar->count().' nomor · '.implode(', ', array_slice($kembar->keys()->all(), 0, 3)),
            'uraian'   => $kembar->isEmpty()
                ? 'Setiap sertifikat memegang nomor yang berbeda.'
                : 'Nomor yang sama terpakai lebih dari sekali — peninggalan penghitung lama yang '
                  .'menghitung jumlah baris, bukan nomor tertinggi. Kedua lembarnya lolos '
                  .'verifikasi barcode, dan tidak ada cara membedakan mana yang asli dari '
                  .'nomornya saja.',
            'tindakan' => $kembar->isEmpty() ? null
                : 'Terbitkan ulang salah satu lembarnya sehingga memperoleh nomor baru, dan '
                  .'catat penggantiannya. Penomoran sekarang sudah melanjutkan dari yang '
                  .'tertinggi, jadi kejadian ini tidak akan berulang.',
        ];
    }

    /**
     * Baris tanpa pemilik pada tabel yang punya company_id.
     *
     * Batas antar perusahaan menganggap company_id NULL sebagai "milik
     * bersama", sehingga barisnya terlihat oleh SEMUA perusahaan. Itu
     * benar untuk acuan yang memang dipakai bersama, dan salah untuk
     * data lapangan yang kehilangan pemiliknya — dan keduanya tidak
     * dapat dibedakan oleh aturannya, hanya oleh orang yang melihat
     * daftarnya.
     */
    private static function tanpaPemilik(): array
    {
        $abaikan = ['companies', 'users', 'migrations', 'sessions', 'cache', 'cache_locks',
                    'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens',
                    'signatories', 'activity_log'];

        $temuan = [];
        $total = 0;

        foreach (self::tabelBerpemilik() as $tabel) {
            if (in_array($tabel, $abaikan, true)) continue;

            $n = DB::table($tabel)->whereNull('company_id')->count();

            if ($n > 0) { $temuan[$tabel] = $n; $total += $n; }
        }

        arsort($temuan);

        return [
            'kelompok' => 'Keutuhan data',
            'judul'    => 'Baris tanpa perusahaan',
            'keadaan'  => $total === 0 ? self::AMAN : ($total > 100 ? self::GAWAT : self::PERHATIAN),
            'nilai'    => $total === 0 ? 'tidak ada'
                : $total.' baris di '.count($temuan).' tabel · '
                  .implode(', ', array_map(fn ($t, $n) => "{$t} {$n}",
                        array_slice(array_keys($temuan), 0, 3),
                        array_slice(array_values($temuan), 0, 3))),
            'uraian'   => $total === 0
                ? 'Setiap baris data melekat pada satu perusahaan.'
                : 'Baris tanpa company_id terlihat oleh SETIAP perusahaan. Untuk acuan yang '
                  .'memang dipakai bersama itu disengaja; untuk data lapangan itu berarti data '
                  .'satu perusahaan terbaca oleh perusahaan lain — dan aturannya tidak dapat '
                  .'membedakan keduanya, hanya orang yang melihat daftarnya yang dapat.',
            'tindakan' => $total === 0 ? null
                : 'Periksa tabel di atas satu per satu. Yang memang acuan bersama biarkan; yang '
                  .'data lapangan tetapkan pemiliknya lewat konsol basis data.',
        ];
    }

    private static function penggunaYatim(): array
    {
        $n = User::query()->whereNull('company_id')
            ->where('is_admin', false)->where('active', true)->count();

        return [
            'kelompok' => 'Keutuhan data',
            'judul'    => 'Pengguna tanpa perusahaan',
            'keadaan'  => $n === 0 ? self::AMAN : self::PERHATIAN,
            'nilai'    => $n.' akun aktif',
            'uraian'   => $n
                ? 'Akun aktif yang bukan administrator dan tidak terikat perusahaan mana pun. '
                  .'Ia hanya melihat data milik bersama, sehingga seluruh modul tampak kosong '
                  .'baginya — dan yang tampak kosong biasanya dilaporkan sebagai "sistemnya '
                  .'rusak", bukan sebagai "akun saya belum diatur".'
                : 'Setiap akun aktif non-administrator terikat pada satu perusahaan.',
            'tindakan' => $n ? 'Tetapkan perusahaannya di Kelola Pengguna.' : null,
        ];
    }

    /**
     * Baris yang sudah diajukan tetapi tidak pernah ditinjau.
     *
     * Yang menunggu tinjauan tidak masuk hitungan KPI mana pun. Selama
     * ia menggantung, angka yang dilihat orang bukan angka yang salah
     * melainkan angka yang KURANG — dan kurangnya tidak kelihatan dari
     * angkanya sendiri.
     */
    private static function tertahan(): array
    {
        $batas = now()->subDays(7);
        $temuan = [];
        $total = 0;

        foreach (self::tabelBeralur() as $tabel) {
            $n = DB::table($tabel)->where('status', Alur::DIAJUKAN)
                ->where('diajukan_pada', '<', $batas)->count();

            if ($n > 0) { $temuan[$tabel] = $n; $total += $n; }
        }

        arsort($temuan);

        return [
            'kelompok' => 'Keutuhan data',
            'judul'    => 'Tertahan menunggu tinjauan',
            'keadaan'  => $total === 0 ? self::AMAN : ($total > 50 ? self::GAWAT : self::PERHATIAN),
            'nilai'    => $total === 0 ? 'tidak ada'
                : $total.' baris lebih dari 7 hari · '
                  .implode(', ', array_map(fn ($t, $n) => "{$t} {$n}",
                        array_slice(array_keys($temuan), 0, 3),
                        array_slice(array_values($temuan), 0, 3))),
            'uraian'   => $total === 0
                ? 'Tidak ada pengajuan yang menggantung lebih dari sepekan.'
                : 'Data yang menunggu tinjauan tidak masuk hitungan KPI mana pun. Angka yang '
                  .'dilihat orang karena itu bukan angka yang salah melainkan angka yang KURANG '
                  .'— dan kurangnya tidak kelihatan dari angkanya sendiri.',
            'tindakan' => $total === 0 ? null
                : 'Tagih peninjauannya kepada Kepala Teknik Tambang; tiap modul punya daftar '
                  .'"menunggu tinjauan" pada halamannya.',
        ];
    }

    /* ═══════════════ perkakas ═══════════════ */

    /** Tabel yang punya kolom company_id. */
    private static function tabelBerpemilik(): array
    {
        return array_keys(array_filter(
            self::petaKolom(),
            fn ($kolom) => in_array('company_id', $kolom, true),
        ));
    }

    /** Tabel yang memakai alur tinjauan. */
    private static function tabelBeralur(): array
    {
        return array_keys(array_filter(
            self::petaKolom(),
            fn ($kolom) => in_array('status', $kolom, true)
                        && in_array('diajukan_pada', $kolom, true),
        ));
    }

    /**
     * Peta tabel => nama kolomnya, dibaca sekali saja.
     *
     * Percobaan pertama memanggil Schema::hasColumn() per tabel di dalam
     * dua penyaring terpisah. Tiap panggilan itu satu kueri skema, dan
     * dengan tabel sebanyak ini ongkosnya 512 kueri untuk satu kali
     * diagnosa — bukan kesalahan hasil, melainkan ongkos yang menempel
     * pada halaman Pusat Kendali yang hanya ingin menampilkan satu
     * lencana ringkasan.
     *
     * @return array<string,list<string>>
     */
    private static function petaKolom(): array
    {
        if (self::$petaKolom !== null) return self::$petaKolom;

        $peta = [];
        foreach (self::semuaTabel() as $t) {
            try {
                $peta[$t] = Schema::getColumnListing($t);
            } catch (\Throwable) {
                // Tabel yang hilang di tengah jalan tidak boleh
                // menghentikan pemetaan tabel lainnya.
                $peta[$t] = [];
            }
        }

        return self::$petaKolom = $peta;
    }

    /**
     * Nama seluruh tabel.
     *
     * Sengaja TIDAK di-cache lintas permintaan: tombol "Jalankan
     * migrasi" ada di halaman yang sama, dan daftar tabel yang basi
     * sesudahnya membuat diagnosa melaporkan keadaan sebelum migrasi
     * seolah keadaan sesudahnya.
     */
    private static function semuaTabel(): array
    {
        return array_map(
            fn ($t) => is_array($t) ? array_values($t)[0] : $t->name ?? $t,
            array_map(fn ($t) => (array) $t, Schema::getTables()),
        );
    }

    /**
     * Lupakan pemetaan skema.
     *
     * Dipanggil sesudah migrasi berjalan: tombol "Jalankan migrasi" ada
     * di halaman yang sama, dan pemetaan yang basi sesudahnya membuat
     * diagnosa melaporkan keadaan sebelum migrasi seolah keadaan
     * sesudahnya — tepat pada saat orang sedang memeriksa apakah
     * perbaikannya berhasil.
     */
    public static function lupakanSkema(): void
    {
        self::$petaKolom = null;
    }

    private static function lewat(string $kelompok, string $judul, string $sebab): array
    {
        return [
            'kelompok' => $kelompok,
            'judul'    => $judul,
            'keadaan'  => self::TAK_TAHU,
            'nilai'    => $sebab,
            'uraian'   => 'Pemeriksaan ini tidak dapat dijalankan pada keadaan sekarang.',
            'tindakan' => null,
        ];
    }

    /** Baca hanya N bita terakhir sebuah berkas. */
    private static function ekorBerkas(string $berkas, int $bita): string
    {
        $b = filesize($berkas);
        $f = fopen($berkas, 'rb');

        if (!$f) return '';

        if ($b > $bita) fseek($f, -$bita, SEEK_END);

        $isi = stream_get_contents($f);
        fclose($f);

        return $isi ?: '';
    }

    private static function ukuran(float $b): string
    {
        $u = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($b >= 1024 && $i < 4) { $b /= 1024; $i++; }

        return round($b, 1).' '.$u[$i];
    }
}
