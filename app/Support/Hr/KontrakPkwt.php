<?php

namespace App\Support\Hr;

use App\Models\Hr\{Absensi, Kontrak, Upah};
use App\Models\Miners\Pekerja;
use App\Support\Waktu;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aturan PKWT menurut PP 35/2021.
 *
 * YANG DIPERIKSA ADALAH RANTAINYA, BUKAN BARISNYA. Hampir seluruh
 * pelanggaran PKWT berbentuk sama: tiap kontrak sendiri-sendiri patuh,
 * dan jumlahnya yang tidak. Dua tahun ditambah dua tahun ditambah dua
 * tahun adalah tiga kontrak yang seluruhnya di bawah lima tahun, dan
 * satu hubungan kerja enam tahun yang sejak bulan keenam puluh satu
 * sudah berubah menjadi PKWTT demi hukum — tanpa satu pun dokumen yang
 * menyebutkannya.
 *
 * AKIBAT PELANGGARANNYA BUKAN DENDA, MELAINKAN PERUBAHAN JENIS. PKWT
 * yang melanggar tidak menjadi PKWT yang bermasalah; ia menjadi PKWTT,
 * dengan pesangon dan segala yang mengikutinya. Karena itu temuan di
 * sini disebut apa adanya, dan tidak satu pun disembunyikan di balik
 * kata "peringatan".
 *
 * KELASNYA MEMERIKSA DAN MENGHITUNG, TIDAK MENYIMPAN. Yang menulis ke
 * basis data adalah JalurKontrak; pemisahan itu yang membuat aturan di
 * sini dapat diuji tanpa satu baris pun dibuat.
 */
class KontrakPkwt
{
    /**
     * Jangka waktu keseluruhan PKWT beserta perpanjangannya — pasal 8.
     *
     * Lima tahun, dan yang dihitung adalah SELURUH rangkaiannya.
     */
    public const MAKS_BULAN = 60;

    /** Masa kerja terpendek yang berhak uang kompensasi — pasal 15 ayat (1). */
    public const MIN_BULAN_KOMPENSASI = 1;

    /** Satu bulan upah untuk tiap dua belas bulan masa kerja — pasal 16. */
    public const BULAN_PENUH = 12;

    /** Masa percobaan PKWTT paling lama tiga bulan — UU 13/2003 pasal 60. */
    public const MAKS_PERCOBAAN_HARI = 90;

    /** PKWT harian: kurang dari 21 hari sebulan — pasal 10 ayat (2). */
    public const HARIAN_MAKS_HARI = 21;

    /** Tiga bulan berturut-turut, lalu berubah menjadi PKWTT — pasal 10 ayat (3). */
    public const HARIAN_BULAN_BERUNTUN = 3;

    /** Pencatatan ke instansi ketenagakerjaan — pasal 14 ayat (2). */
    public const CATAT_HARI_KERJA = 3;

    /* ═══════════════════ masa kerja ═══════════════════ */

    /**
     * Panjang sebuah kontrak dalam bulan, sebagai pecahan.
     *
     * DIHITUNG PER BULAN KALENDER, BUKAN PER HARI DIBAGI RATA-RATA.
     * Pembagi 30,4375 hari tampak lebih teliti dan justru lebih salah:
     * kontrak 1 Januari sampai 30 Juni berisi 181 hari, dan dibagi
     * rata-rata ia menjadi 5,95 bulan — padahal ia enam bulan kalender
     * penuh. Kesalahannya berat sebelah: separuh tahun yang pendek
     * selalu kurang dihitung, sehingga setiap kontrak yang berakhir di
     * pertengahan tahun membayar kompensasi kurang dari haknya.
     *
     * INKLUSIF pada kedua ujungnya, sebab hari terakhir kontrak adalah
     * hari orang itu masih bekerja. Satu bulan penuh berjalan dari hari
     * D bulan ini sampai sehari sebelum hari D bulan depan.
     */
    public static function bulan(Carbon $mulai, ?Carbon $selesai): float
    {
        if ($selesai === null) return 0.0;

        $a = $mulai->copy()->startOfDay();
        $b = $selesai->copy()->startOfDay();

        if ($b->lt($a)) return 0.0;

        /* Bulan penuh yang muat di dalam rentangnya. Tanpa overflow:
           kontrak yang mulai 31 Januari berakhir bulan penuhnya pada 27
           Februari, bukan tumpah ke 3 Maret. */
        $penuh = 0;
        while ($a->copy()->addMonthsNoOverflow($penuh + 1)->subDay()->lte($b)) {
            $penuh++;

            // Rantai lima tahun berisi enam puluhan bulan; batas ini
            // hanya menjaga data rusak yang tanggal selesainya ratusan
            // tahun ke depan agar tidak memutar tanpa ujung.
            if ($penuh > 1200) break;
        }

        $sisaMulai = $a->copy()->addMonthsNoOverflow($penuh);

        /* Sisanya dibandingkan dengan panjang bulan berjalan ITU
           SENDIRI — 20 hari di bulan Februari lebih dari 20 hari di
           bulan Januari, dan pembagi tetap membuat keduanya sama. */
        $sisaHari    = $sisaMulai->diffInDays($b) + 1;
        $panjangSisa = $sisaMulai->diffInDays($sisaMulai->copy()->addMonthNoOverflow());

        if ($sisaHari <= 0 || $panjangSisa <= 0) return round((float) $penuh, 4);

        return round($penuh + ($sisaHari / $panjangSisa), 4);
    }

    /**
     * Tanggal sesudah sekian bulan berjalan dari sebuah titik.
     *
     * Kebalikan dari `bulan`, dan memakai aritmetika yang sama supaya
     * keduanya tidak pernah berselisih — titik yang dihitung di sini
     * lalu diukur kembali dengan `bulan` harus menghasilkan angka yang
     * sama.
     */
    public static function tambahBulan(Carbon $dari, float $bulan): Carbon
    {
        $penuh = (int) floor($bulan);
        $titik = $dari->copy()->startOfDay()->addMonthsNoOverflow($penuh);

        $pecah = $bulan - $penuh;

        if ($pecah <= 0) return $titik;

        $panjang = $titik->diffInDays($titik->copy()->addMonthNoOverflow());

        return $titik->addDays((int) round($pecah * $panjang));
    }

    /** Panjang seluruh rantai dalam bulan. */
    public static function bulanRantai(Kontrak $k): float
    {
        return round($k->rantai()
            ->filter(fn (Kontrak $r) => $r->pkwt())
            ->sum(fn (Kontrak $r) => self::bulan($r->mulai, self::akhirNyata($r))), 4);
    }

    /**
     * Tanggal kontrak benar-benar berakhir.
     *
     * Kontrak yang diputus di tengah berakhir pada hari pemutusannya,
     * bukan pada tanggal yang tertulis. Dipakai apa adanya, masa kerja
     * yang dihitung adalah masa yang DIJANJIKAN, dan uang kompensasi
     * pasal 17 — yang justru harus sebesar masa yang DIJALANI — menjadi
     * terlalu besar pada tiap pemutusan.
     */
    public static function akhirNyata(Kontrak $k): ?Carbon
    {
        if ($k->status === 'diputus') {
            return $k->kompensasi_dibayar_pada ?? $k->updated_at?->copy() ?? $k->selesai;
        }

        return $k->selesai;
    }

    /* ═══════════════════ uang kompensasi ═══════════════════ */

    /**
     * Uang kompensasi akhir kontrak — pasal 15 sampai 17.
     *
     * Proporsional pada kedua arah: masa kerja dua belas bulan berhak
     * satu bulan upah, dan masa kerja di atas maupun di bawah itu
     * dihitung dengan perbandingan yang sama. Yang TIDAK proporsional
     * hanyalah ambang bawahnya — di bawah satu bulan, haknya nol, bukan
     * sepersekian.
     *
     * UPAHNYA DIAMBIL PADA TANGGAL BERAKHIR, bukan pada tanggal tanda
     * tangan (pasal 16 ayat 4 menyebut upah pokok dan tunjangan tetap,
     * dan yang berlaku adalah yang terakhir). Pekerja yang naik upah di
     * tengah kontrak berhak atas kompensasi menurut upah barunya.
     *
     * @return array{berhak:bool,alasan:?string,upah:float,bulan:float,nilai:float}
     */
    public static function kompensasi(Kontrak $k): array
    {
        $kosong = ['berhak' => false, 'alasan' => null, 'upah' => 0.0, 'bulan' => 0.0, 'nilai' => 0.0];

        if (! $k->pkwt()) {
            return [...$kosong, 'alasan' => 'PKWTT tidak berhak atas uang kompensasi.'];
        }

        $akhir = self::akhirNyata($k);

        if ($akhir === null) {
            return [...$kosong, 'alasan' => 'Kontrak belum punya tanggal berakhir.'];
        }

        $bulan = self::bulan($k->mulai, $akhir);

        if ($bulan < self::MIN_BULAN_KOMPENSASI) {
            return [...$kosong, 'bulan' => $bulan,
                'alasan' => 'Masa kerja kurang dari satu bulan — pasal 15 ayat (1).'];
        }

        $pekerja = $k->pekerja ?? Pekerja::withoutGlobalScopes()->find($k->pekerja_id);

        if ($pekerja === null) {
            return [...$kosong, 'bulan' => $bulan, 'alasan' => 'Pekerjanya tidak ditemukan.'];
        }

        $upah = Upah::pada($pekerja, $akhir);

        // Tanpa upah yang tercatat, angkanya TIDAK ditebak. Kompensasi
        // nol yang tampil sebagai angka terlihat sama meyakinkannya
        // dengan kompensasi nol yang memang haknya.
        if ($upah === null) {
            return [...$kosong, 'bulan' => $bulan,
                'alasan' => 'Upah pada tanggal berakhir belum tercatat.'];
        }

        $dasar = round($upah->pokok + $upah->tunjangan_tetap, 2);

        return [
            'berhak' => true,
            'alasan' => null,
            'upah'   => $dasar,
            'bulan'  => $bulan,
            'nilai'  => round($dasar * ($bulan / self::BULAN_PENUH), 2),
        ];
    }

    /* ═══════════════════ pemeriksaan ═══════════════════ */

    /**
     * Seluruh pelanggaran pada sebuah kontrak dan rantainya.
     *
     * @return list<array{kunci:string,berat:string,pesan:string,dasar:string}>
     */
    public static function periksa(Kontrak $k): array
    {
        $temuan = [];

        $tambah = function (string $kunci, string $berat, string $pesan, string $dasar) use (&$temuan) {
            $temuan[] = compact('kunci', 'berat', 'pesan', 'dasar');
        };

        /* ── batas lima tahun, atas seluruh rantai ── */
        if ($k->pkwt()) {
            $total = self::bulanRantai($k);

            if ($total > self::MAKS_BULAN) {
                $tahun = round($total / 12, 1);
                $tambah('lewat_lima_tahun', 'gawat',
                    "Rangkaian PKWT sudah {$tahun} tahun, melewati batas lima tahun. "
                    .'Sejak bulan keenam puluh satu hubungan kerjanya PKWTT demi hukum.',
                    'PP 35/2021 pasal 8');
            }
        }

        /* ── alasan pasal 5 bagi PKWT jangka waktu ── */
        if ($k->jenis === 'pkwt_jangka' && ! array_key_exists((string) $k->alasan, Kontrak::ALASAN)) {
            $tambah('tanpa_alasan', 'gawat',
                'PKWT jangka waktu tanpa salah satu alasan pasal 5. '
                .'Tanpa alasan yang sah, kontraknya PKWTT sejak hari pertama.',
                'PP 35/2021 pasal 5');
        }

        /* ── batasan "selesai" wajib tertulis ── */
        if ($k->jenis === 'pkwt_selesai' && trim((string) $k->batasan_selesai) === '') {
            $tambah('tanpa_batasan', 'gawat',
                'PKWT selesainya pekerjaan tanpa batasan tertulis tentang kapan '
                .'pekerjaan itu dinyatakan selesai — tidak ada yang dapat mengakhirinya.',
                'PP 35/2021 pasal 10 ayat (1)');
        }

        /* ── masa percobaan ── */
        if ($k->pkwt() && $k->masa_percobaan_hari > 0) {
            $tambah('percobaan_pkwt', 'gawat',
                "Masa percobaan {$k->masa_percobaan_hari} hari disyaratkan pada PKWT. "
                .'Syarat itu batal demi hukum dan masa kerjanya tetap dihitung.',
                'PP 35/2021 pasal 12');
        }

        if ($k->jenis === 'pkwtt' && $k->masa_percobaan_hari > self::MAKS_PERCOBAAN_HARI) {
            $tambah('percobaan_panjang', 'ingat',
                "Masa percobaan {$k->masa_percobaan_hari} hari melewati batas tiga bulan.",
                'UU 13/2003 pasal 60 ayat (1)');
        }

        /* ── pencatatan ke instansi ketenagakerjaan ── */
        if ($k->pkwt() && $k->ditandatangani_pada !== null && $k->dicatatkan_pada === null) {
            $lewat = self::hariKerjaAntara($k->ditandatangani_pada, Waktu::kini());

            if ($lewat > self::CATAT_HARI_KERJA) {
                $tambah('belum_dicatatkan', 'ingat',
                    "Ditandatangani {$lewat} hari kerja lalu dan belum dicatatkan.",
                    'PP 35/2021 pasal 14 ayat (2)');
            }
        }

        /* ── PKWT harian yang sudah melampaui batasnya ── */
        if ($k->jenis === 'pkwt_harian') {
            $beruntun = self::bulanHarianTerlampaui($k);

            if (count($beruntun) >= self::HARIAN_BULAN_BERUNTUN) {
                $daftar = implode(', ', array_slice($beruntun, 0, 6));
                $tambah('harian_jadi_pkwtt', 'gawat',
                    'Bekerja 21 hari atau lebih selama '.count($beruntun)
                    ." bulan berturut-turut ({$daftar}). Perjanjian harian ini "
                    .'berubah menjadi PKWTT demi hukum.',
                    'PP 35/2021 pasal 10 ayat (2) dan (3)');
            }
        }

        return $temuan;
    }

    /**
     * Bulan-bulan berturut-turut seorang pekerja harian melewati 21 hari.
     *
     * DIHITUNG DARI ABSENSI, BUKAN DARI ROSTER. Yang mengubah perjanjian
     * harian menjadi PKWTT adalah hari yang benar-benar dijalani; roster
     * hanya menyatakan niat, dan orang yang dipanggil masuk di luar
     * jadwal tetap terhitung. Justru pemanggilan di luar jadwal itu yang
     * paling sering membuat batasnya terlampaui tanpa ada yang sadar.
     *
     * Yang dikembalikan adalah DERET TERPANJANG yang beruntun, bukan
     * seluruh bulan yang melewati batas: tiga bulan terpisah oleh bulan
     * sepi di antaranya tidak memenuhi "berturut-turut".
     *
     * @return list<string> bulan dalam bentuk YYYY-MM
     */
    public static function bulanHarianTerlampaui(Kontrak $k): array
    {
        $akhir = self::akhirNyata($k) ?? Waktu::kini();

        $baris = Absensi::withoutGlobalScopes()
            ->where('company_id', $k->company_id)
            ->where('pekerja_id', $k->pekerja_id)
            ->bekerja()
            ->antara($k->mulai->toDateString(), $akhir->toDateString())
            ->get(['tanggal']);

        $per = [];
        foreach ($baris as $b) {
            $per[$b->tanggal->format('Y-m')] = ($per[$b->tanggal->format('Y-m')] ?? 0) + 1;
        }

        ksort($per);

        $terpanjang = [];
        $berjalan   = [];
        $sebelum    = null;

        foreach ($per as $bulan => $hari) {
            if ($hari < self::HARIAN_MAKS_HARI) {
                $berjalan = [];
                $sebelum  = $bulan;
                continue;
            }

            // "Berturut-turut" menuntut bulan yang benar-benar bersebelahan.
            // Bulan sepi di antaranya tidak muncul sebagai baris sama
            // sekali — ia hanya tidak ada — sehingga tanpa pemeriksaan ini
            // Januari dan Maret terbaca sebagai dua bulan beruntun.
            if ($sebelum !== null && $berjalan !== []
                && Carbon::parse($sebelum.'-01')->addMonthNoOverflow()->format('Y-m') !== $bulan) {
                $berjalan = [];
            }

            $berjalan[] = $bulan;
            $sebelum    = $bulan;

            if (count($berjalan) > count($terpanjang)) $terpanjang = $berjalan;
        }

        return $terpanjang;
    }

    /**
     * Hari kerja di antara dua tanggal, tidak menghitung Sabtu dan Minggu.
     *
     * Hari libur nasional TIDAK diperhitungkan — daftarnya berubah tiap
     * tahun lewat SKB tiga menteri dan belum ada di sistem ini. Akibatnya
     * hitungan ini sedikit lebih ketat daripada peraturannya, dan arah
     * itu yang dipilih: yang salah adalah memberi tahu terlambat.
     */
    public static function hariKerjaAntara(Carbon $dari, Carbon $sampai): int
    {
        $a = $dari->copy()->startOfDay();
        $b = $sampai->copy()->startOfDay();

        if ($b->lte($a)) return 0;

        $n = 0;
        for ($h = $a->copy()->addDay(); $h->lte($b); $h->addDay()) {
            if (! $h->isWeekend()) $n++;
        }

        return $n;
    }

    /**
     * Kontrak yang berakhir dalam sekian hari ke depan.
     *
     * @return Collection<int, Kontrak>
     */
    public static function akanBerakhir(?int $company, int $hari = 60): Collection
    {
        $kini = Waktu::kini();

        return Kontrak::withoutGlobalScopes()
            ->where('company_id', $company)
            ->hidup()
            ->berakhirAntara($kini->toDateString(), $kini->copy()->addDays($hari)->toDateString())
            ->with('pekerja')
            ->orderBy('selesai')
            ->get();
    }
}
