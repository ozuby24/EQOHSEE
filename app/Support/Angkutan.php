<?php

namespace App\Support;

/**
 * Hitungan pendukung pengangkutan dan pengaturan armada (dispatch).
 *
 * Modul ini menjawab satu pertanyaan yang tidak dijawab modul mana pun:
 * mengapa tonase shift kemarin keluar sebesar itu. Mine Operations
 * mencatat berapa yang terangkut per pit; di sini dicatat bagaimana ia
 * terangkut — berapa truk melayani satu excavator, ke mana waktu satu
 * putaran habis, dan berapa lama truk berdiri antre di muka gali.
 *
 * Dua hal membuat perhitungan di sini mudah keliru, dan keduanya sengaja
 * dipisahkan tegas di bawah:
 *
 *  - Waktu antre BUKAN bagian dari waktu edar yang dipakai menghitung
 *    match factor. Antre adalah AKIBAT dari ketidakseimbangan, bukan
 *    sebabnya. Memasukkannya ke penyebut membuat match factor selalu
 *    mendekati satu — armada yang kelebihan sepuluh truk pun akan
 *    terbaca seimbang, sebab kelebihannya sudah tertelan menjadi antrean
 *    di dalam angka yang sedang dinilai.
 *
 *  - Muatan berlebih bukan soal efisiensi melainkan soal rem. Truk yang
 *    kelebihan muatan menuruni jalan angkut membawa energi yang harus
 *    diserap retarder, dan kelebihan itu tidak terasa oleh pengemudinya
 *    sampai retardernya tidak lagi cukup. Karena itu kepatuhan muatan
 *    diperiksa dengan kaidah yang mengikat tiap muatan satu per satu,
 *    bukan dengan rata-ratanya saja.
 */
final class Angkutan
{
    /* ═══════════ keseimbangan armada ═══════════ */

    /**
     * Rentang match factor yang masih disebut seimbang.
     *
     * Di luar rentang ini salah satu pihak menunggu: di bawah batas
     * bawah excavator menganggur menunggu truk, di atas batas atas truk
     * mengantre menunggu excavator. Keduanya kehilangan produksi, tetapi
     * hanya yang kedua menumpuk kendaraan di muka gali.
     */
    public const MF_BAWAH = 0.85;
    public const MF_ATAS  = 1.15;

    /**
     * Match factor: keseimbangan jumlah truk terhadap alat muat.
     *
     *   MF = (Nt · Tm) / (Na · Te)
     *
     * Nt jumlah truk, Na jumlah alat muat, Tm waktu memuat satu truk,
     * Te waktu edar satu truk TANPA antre.
     *
     * MF = 1 berarti kedatangan truk tepat menghabiskan kapasitas alat
     * muat. Angka ini tidak pernah menjadi sasaran yang harus dikejar
     * sampai persis satu: armada yang sengaja sedikit di atas satu
     * menjaga alat muat tetap terisi dengan harga antrean pendek, dan
     * pada tambang yang alat muatnya jauh lebih mahal daripada truknya
     * itu memang pilihan yang benar. Yang perlu diketahui bukan angkanya
     * melainkan ke arah mana ia menyimpang, dan berapa besar.
     */
    public static function matchFactor(
        int $jumlahTruk, float $waktuMuatMenit,
        int $jumlahAlatMuat, float $waktuEdarMenit
    ): ?float {
        if ($jumlahTruk <= 0 || $jumlahAlatMuat <= 0
            || $waktuMuatMenit <= 0 || $waktuEdarMenit <= 0) return null;

        return round(($jumlahTruk * $waktuMuatMenit) / ($jumlahAlatMuat * $waktuEdarMenit), 3);
    }

    /** Penilaian arah penyimpangan match factor. */
    public static function bacaMatchFactor(?float $mf): array
    {
        if ($mf === null) {
            return ['kelas' => 'tak-diketahui', 'label' => 'Belum dapat dihitung',
                    'ket' => 'Komponen waktu edar belum lengkap.'];
        }

        if ($mf < self::MF_BAWAH) {
            return ['kelas' => 'kurang-truk', 'label' => 'Truk kurang',
                    'ket' => 'Alat muat menganggur menunggu truk; kapasitas gali tidak terpakai penuh.'];
        }

        if ($mf > self::MF_ATAS) {
            return ['kelas' => 'lebih-truk', 'label' => 'Truk berlebih',
                    'ket' => 'Truk mengantre di muka gali; bahan bakar terbakar tanpa tonase, dan kendaraan menumpuk di area alat gali bekerja.'];
        }

        return ['kelas' => 'seimbang', 'label' => 'Seimbang',
                'ket' => 'Kedatangan truk sepadan dengan kapasitas alat muat.'];
    }

    /* ═══════════ waktu edar ═══════════ */

    /**
     * Waktu edar produktif, menit — tanpa antre.
     *
     * Inilah penyebut match factor. Lihat catatan kelas di atas untuk
     * alasan antre tidak ikut dijumlahkan di sini.
     */
    public static function waktuEdar(
        ?float $muat, ?float $angkut, ?float $tumpah, ?float $kembali
    ): ?float {
        $jumlah = (float) $muat + (float) $angkut + (float) $tumpah + (float) $kembali;

        return $jumlah > 0 ? round($jumlah, 3) : null;
    }

    /**
     * Waktu edar nyata, menit — termasuk antre.
     *
     * Inilah yang menentukan berapa rit benar-benar terjadi dalam satu
     * shift, sehingga seluruh hitungan produktivitas memakai angka ini,
     * bukan waktu edar produktif.
     */
    public static function waktuEdarNyata(
        ?float $muat, ?float $angkut, ?float $tumpah, ?float $kembali, ?float $antre
    ): ?float {
        $tanpaAntre = self::waktuEdar($muat, $angkut, $tumpah, $kembali);

        return $tanpaAntre === null ? null : round($tanpaAntre + (float) $antre, 3);
    }

    /** Porsi antre terhadap waktu edar nyata, persen. */
    public static function porsiAntre(
        ?float $muat, ?float $angkut, ?float $tumpah, ?float $kembali, ?float $antre
    ): ?float {
        $nyata = self::waktuEdarNyata($muat, $angkut, $tumpah, $kembali, $antre);
        if ($nyata === null || $nyata <= 0) return null;

        return round((float) $antre / $nyata * 100, 2);
    }

    /** Porsi antre yang masih wajar, persen dari waktu edar. */
    public const ANTRE_WAJAR_PERSEN = 15.0;

    /**
     * Rincian waktu edar dalam persen, untuk dibaca sekaligus.
     *
     * @return list<array{nama:string,menit:float,persen:float}>
     */
    public static function rincianEdar(
        ?float $muat, ?float $angkut, ?float $tumpah, ?float $kembali, ?float $antre
    ): array {
        $nyata = self::waktuEdarNyata($muat, $angkut, $tumpah, $kembali, $antre);
        if ($nyata === null || $nyata <= 0) return [];

        $bagian = [
            'Muat'    => (float) $muat,
            'Angkut'  => (float) $angkut,
            'Tumpah'  => (float) $tumpah,
            'Kembali' => (float) $kembali,
            'Antre'   => (float) $antre,
        ];

        $keluar = [];
        foreach ($bagian as $nama => $menit) {
            if ($menit <= 0) continue;
            $keluar[] = ['nama' => $nama, 'menit' => round($menit, 2),
                         'persen' => round($menit / $nyata * 100, 2)];
        }

        return $keluar;
    }

    /* ═══════════ produktivitas ═══════════ */

    /**
     * Jumlah rit yang mampu ditempuh satu truk dalam sekian jam kerja.
     *
     * Dibulatkan ke bawah: rit yang tidak selesai sebelum shift berakhir
     * tidak menghasilkan tonase apa pun.
     */
    public static function ritaseTeoritis(float $jamKerja, ?float $waktuEdarNyataMenit): ?int
    {
        if ($jamKerja <= 0 || $waktuEdarNyataMenit === null || $waktuEdarNyataMenit <= 0) return null;

        return (int) floor($jamKerja * 60 / $waktuEdarNyataMenit);
    }

    /** Produktivitas satu truk, ton per jam. */
    public static function produktivitasTruk(float $muatanTon, ?float $waktuEdarNyataMenit): ?float
    {
        if ($muatanTon <= 0 || $waktuEdarNyataMenit === null || $waktuEdarNyataMenit <= 0) return null;

        return round($muatanTon * 60 / $waktuEdarNyataMenit, 2);
    }

    /**
     * Kecepatan rata-rata pulang pergi, km/jam.
     *
     * Rata-rata, dan hanya rata-rata. Angkanya tidak dapat membuktikan
     * tidak ada pelanggaran kecepatan: satu penggal turunan yang dilalui
     * jauh di atas batas tetap menghasilkan rata-rata yang tenang bila
     * penggal lainnya merayap. Yang dapat ditunjukkannya justru
     * sebaliknya — rata-rata yang sudah melewati batas berarti sebagian
     * besar perjalanan memang di atas batas.
     */
    public static function kecepatanRata(float $jarakKm, ?float $angkutMenit, ?float $kembaliMenit): ?float
    {
        $waktu = (float) $angkutMenit + (float) $kembaliMenit;
        if ($jarakKm <= 0 || $waktu <= 0) return null;

        return round($jarakKm * 2 / ($waktu / 60), 2);
    }

    /** Pemakaian jam terjadwal: berapa persen benar-benar bekerja. */
    public static function utilisasi(float $jamKerja, float $jamDelay): ?float
    {
        $terjadwal = $jamKerja + $jamDelay;
        if ($terjadwal <= 0) return null;

        return round($jamKerja / $terjadwal * 100, 2);
    }

    /* ═══════════ kepatuhan muatan ═══════════ */

    /**
     * Kaidah muatan 10/10/20.
     *
     * Kaidah pabrikan truk angkut, dan alasannya bukan penghematan ban:
     *
     *  - rata-rata seluruh muatan tidak melebihi 100% kapasitas nominal;
     *  - tidak lebih dari 10% muatan berada di atas 110%;
     *  - tidak satu pun muatan berada di atas 120%.
     *
     * Yang ketiga bersifat mutlak justru karena yang pertama tidak.
     * Rata-rata yang rapi menyembunyikan satu truk bermuatan 130% yang
     * menuruni jalan angkut dengan retarder di ambangnya, dan truk itulah
     * yang menjadi kejadian — bukan rata-ratanya.
     */
    public const PAYLOAD_RATA_MAKS    = 100.0;
    public const PAYLOAD_LEBIH        = 110.0;
    public const PAYLOAD_PORSI_MAKS   = 10.0;
    public const PAYLOAD_PUNCAK       = 120.0;

    /**
     * Penimbangan minimum sebelum kaidah 10% layak dinilai.
     *
     * Satu muatan di atas 110% dari tiga penimbangan menghasilkan 33%,
     * dan angka itu tidak berarti apa-apa. Batas mutlak 120% tetap
     * berlaku sejak penimbangan pertama — ia menilai satu muatan, bukan
     * sebarannya, sehingga tidak menuntut jumlah sama sekali.
     */
    public const MIN_MUATAN_KEBIJAKAN = 20;

    /**
     * Nilai sekumpulan penimbangan terhadap kaidah 10/10/20.
     *
     * @param  list<float> $muatanTon  hasil timbang tiap rit
     * @param  float       $nominalTon kapasitas nominal truk
     * @return array{n:int,rata:?float,rataPersen:?float,lebih110:int,porsi110:?float,lebih120:int,
     *               lulusRata:?bool,lulusPorsi:?bool,lulusPuncak:?bool,cukupData:bool,tertinggi:?float,
     *               patuh:?bool,alasan:string}
     */
    public static function kepatuhanMuatan(array $muatanTon, float $nominalTon): array
    {
        $sah = array_values(array_filter(
            array_map('floatval', $muatanTon), fn (float $m) => $m > 0
        ));
        $n = count($sah);

        $kosong = [
            'n' => $n, 'rata' => null, 'rataPersen' => null, 'lebih110' => 0, 'porsi110' => null,
            'lebih120' => 0, 'lulusRata' => null, 'lulusPorsi' => null, 'lulusPuncak' => null,
            'cukupData' => false, 'tertinggi' => null, 'patuh' => null,
        ];

        if ($n === 0 || $nominalTon <= 0) {
            return $kosong + ['alasan' => 'Belum ada penimbangan muatan; kepatuhan tidak dapat dinilai.'];
        }

        $rata      = array_sum($sah) / $n;
        $tertinggi = max($sah);
        $lebih110  = count(array_filter($sah, fn (float $m) => $m > $nominalTon * self::PAYLOAD_LEBIH / 100));
        $lebih120  = count(array_filter($sah, fn (float $m) => $m > $nominalTon * self::PAYLOAD_PUNCAK / 100));
        $porsi110  = $lebih110 / $n * 100;
        $cukup     = $n >= self::MIN_MUATAN_KEBIJAKAN;

        // Kaidah puncak dinilai sejak penimbangan pertama; kaidah porsi
        // menunggu jumlah yang layak. Rata-rata ikut menunggu karena ia
        // pun sebuah sebaran, bukan satu kejadian.
        $lulusPuncak = $lebih120 === 0;
        $lulusRata   = $cukup ? $rata <= $nominalTon * self::PAYLOAD_RATA_MAKS / 100 : null;
        $lulusPorsi  = $cukup ? $porsi110 <= self::PAYLOAD_PORSI_MAKS : null;

        $patuh = !$lulusPuncak ? false
            : ($cukup ? ($lulusRata && $lulusPorsi) : null);

        $alasan = match (true) {
            !$lulusPuncak => $lebih120.' muatan melampaui 120% kapasitas nominal — batas mutlak dilanggar.',
            !$cukup       => 'Baru '.$n.' penimbangan; kaidah 10% baru layak dinilai sejak '
                             .self::MIN_MUATAN_KEBIJAKAN.' penimbangan.',
            $patuh        => 'Memenuhi kaidah 10/10/20.',
            default       => 'Sebaran muatan di luar kaidah 10/10/20.',
        };

        return [
            'n' => $n,
            'rata'        => round($rata, 2),
            'rataPersen'  => round($rata / $nominalTon * 100, 1),
            'lebih110'    => $lebih110,
            'porsi110'    => round($porsi110, 1),
            'lebih120'    => $lebih120,
            'lulusRata'   => $lulusRata,
            'lulusPorsi'  => $lulusPorsi,
            'lulusPuncak' => $lulusPuncak,
            'cukupData'   => $cukup,
            'tertinggi'   => round($tertinggi, 2),
            'patuh'       => $patuh,
            'alasan'      => $alasan,
        ];
    }

    /** Muatan dalam persen kapasitas nominal. */
    public static function persenMuatan(float $muatanTon, float $nominalTon): ?float
    {
        if ($nominalTon <= 0 || $muatanTon <= 0) return null;

        return round($muatanTon / $nominalTon * 100, 1);
    }

    /* ═══════════ kehilangan ═══════════ */

    /**
     * Tonase yang hilang karena antre, ton.
     *
     * Waktu antre seluruh truk selama shift dibagi waktu edar produktif
     * memberi jumlah rit yang seharusnya masih sempat ditempuh; dikalikan
     * muatan rata-rata, ia menjadi angka yang dapat dibandingkan dengan
     * target — dan angka dalam ton jauh lebih menggerakkan daripada
     * angka dalam menit.
     */
    public static function tonaseHilangAntre(
        float $menitAntreTotal, ?float $waktuEdarMenit, float $muatanRataTon
    ): ?float {
        if ($menitAntreTotal <= 0 || $waktuEdarMenit === null
            || $waktuEdarMenit <= 0 || $muatanRataTon <= 0) return null;

        return round($menitAntreTotal / $waktuEdarMenit * $muatanRataTon, 2);
    }
}
