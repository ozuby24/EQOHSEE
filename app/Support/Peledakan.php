<?php

namespace App\Support;

/**
 * Hitungan pendukung pengeboran dan peledakan.
 *
 * Sifat bahayanya berbeda dari modul lain, dan itu menentukan bentuk
 * hitungan di sini. Lereng bergerak selama berhari-hari sebelum runtuh;
 * kolam terisi selama berjam-jam sebelum melimpah. Peledakan berlangsung
 * dalam hitungan milidetik, dan seluruh keputusan yang dapat mencegah
 * kecelakaan harus sudah diambil SEBELUM tombolnya ditekan.
 *
 * Karena itu yang paling penting di sini bukan pelaporan melainkan
 * perhitungan di muka: berapa isi bahan peledak maksimum per tundaan
 * agar getaran di rumah terdekat tetap di bawah ambang, dan seberapa
 * jauh radius aman lemparan batu. Keduanya menjawab pertanyaan yang
 * diajukan saat rancangan disusun, bukan saat laporan ditulis.
 *
 * Getaran memakai penskalaan akar (USBM). Tetapan K dan β boleh — dan
 * sebaiknya — dikalibrasi dari pengukuran situs itu sendiri: tetapan
 * umum meleset jauh antar jenis batuan, dan meleset ke arah yang tidak
 * dapat ditebak.
 */
final class Peledakan
{
    /**
     * Tetapan penjalaran getaran bawaan (USBM rata-rata).
     *
     * PPV(mm/s) = K · (D/√W)^(−β), D dalam meter, W dalam kilogram.
     *
     * Dipakai HANYA selama situs belum punya pengukurannya sendiri.
     * Pada batuan masif nilainya bisa jauh lebih tinggi, pada batuan
     * berkekar rapat jauh lebih rendah — dan keduanya menyimpang ke arah
     * yang berlawanan, sehingga tetapan umum tidak dapat disebut aman
     * maupun konservatif.
     */
    public const K_BAWAAN    = 1140.0;
    public const BETA_BAWAAN = 1.6;

    /** Pengukuran minimum sebelum kalibrasi situs boleh dipercaya. */
    public const MIN_TITIK_KALIBRASI = 5;

    /**
     * Ambang getaran bawaan, mm/s.
     *
     * Angka acuan, bukan ketentuan: batasnya ditetapkan izin lingkungan
     * dan jenis bangunan yang dilindungi, dan berganti mengikuti
     * peraturan yang berlaku. Disimpan sebagai bawaan yang dapat ditimpa.
     */
    public const PPV_BANGUNAN_PEKA = 3.0;
    public const PPV_PERMUKIMAN    = 5.0;
    public const PPV_INDUSTRI      = 12.5;

    /* ═══════════ pemakaian bahan peledak ═══════════ */

    /**
     * Powder factor: kilogram bahan peledak per meter kubik batuan.
     *
     * Terlalu rendah menghasilkan bongkah besar yang menyulitkan alat
     * gali dan peremuk; terlalu tinggi membuang bahan peledak sekaligus
     * memperbesar getaran dan lemparan batu. Keduanya mahal, tetapi
     * hanya satu di antaranya yang berbahaya.
     */
    public static function powderFactor(float $bahanPeledakKg, float $volumeBcm): ?float
    {
        if ($volumeBcm <= 0) return null;

        return round($bahanPeledakKg / $volumeBcm, 4);
    }

    /** Volume batuan yang dilayani satu lubang, m³. */
    public static function volumePerLubang(float $burdenM, float $spasiM, float $tinggiJenjangM): ?float
    {
        if ($burdenM <= 0 || $spasiM <= 0 || $tinggiJenjangM <= 0) return null;

        return round($burdenM * $spasiM * $tinggiJenjangM, 3);
    }

    /* ═══════════ getaran ═══════════ */

    /**
     * Jarak skala: D/√W.
     *
     * Pembaginya isi bahan peledak per TUNDAAN, bukan seluruh isi
     * lubang dalam satu peledakan. Memakai total mengubah angkanya
     * berlipat-lipat, dan justru itulah gunanya penundaan — memecah satu
     * ledakan besar menjadi banyak ledakan kecil yang getarannya tidak
     * saling menumpuk.
     */
    public static function jarakSkala(float $jarakM, float $isiPerTundaKg): ?float
    {
        if ($jarakM <= 0 || $isiPerTundaKg <= 0) return null;

        return round($jarakM / sqrt($isiPerTundaKg), 4);
    }

    /** Perkiraan getaran puncak, mm/s. */
    public static function ppvPerkiraan(
        float $jarakM, float $isiPerTundaKg,
        ?float $k = null, ?float $beta = null
    ): ?float {
        $sd = self::jarakSkala($jarakM, $isiPerTundaKg);
        if ($sd === null || $sd <= 0) return null;

        $k ??= self::K_BAWAAN;
        $beta ??= self::BETA_BAWAAN;

        return round($k * $sd ** (-$beta), 3);
    }

    /**
     * Isi bahan peledak maksimum per tundaan agar getaran tetap di bawah ambang.
     *
     * Inilah hitungan yang paling berguna pada modul ini, sebab ia
     * dijawab sebelum peledakan disusun, bukan sesudahnya. Diturunkan
     * dari rumus yang sama:
     *
     *   PPV = K·(D/√W)^(−β)  →  W = [ D / (K/PPV)^(1/β) ]²
     */
    public static function isiMaksPerTunda(
        float $jarakM, float $ppvAmbang,
        ?float $k = null, ?float $beta = null
    ): ?float {
        if ($jarakM <= 0 || $ppvAmbang <= 0) return null;

        $k ??= self::K_BAWAAN;
        $beta ??= self::BETA_BAWAAN;
        if ($beta <= 0 || $k <= 0) return null;

        $sdPerlu = ($k / $ppvAmbang) ** (1 / $beta);
        if ($sdPerlu <= 0) return null;

        return round(($jarakM / $sdPerlu) ** 2, 2);
    }

    /**
     * Kalibrasi tetapan situs dari pengukuran sendiri.
     *
     * ln(PPV) = ln(K) − β·ln(SD), jadi regresi lurus pada kedua
     * logaritma memberi K dari potongannya dan β dari kemiringannya.
     *
     * Hasil yang tidak masuk akal ditolak alih-alih dikembalikan: β
     * negatif berarti getaran menguat seiring jarak, dan tetapan seperti
     * itu — bila dipakai menghitung isi maksimum — menghasilkan angka
     * yang justru berbahaya.
     *
     * @param  list<array{jarak_m:float,isi_kg:float,ppv:float}> $ukur
     * @return array{k:?float,beta:?float,r2:?float,n:int,dapatDipakai:bool,alasan:string}
     */
    public static function kalibrasi(array $ukur): array
    {
        $titik = [];
        foreach ($ukur as $u) {
            $sd = self::jarakSkala((float) $u['jarak_m'], (float) $u['isi_kg']);
            if ($sd === null || $sd <= 0 || ($u['ppv'] ?? 0) <= 0) continue;
            $titik[] = ['x' => log($sd), 'y' => log((float) $u['ppv'])];
        }

        $n = count($titik);
        if ($n < self::MIN_TITIK_KALIBRASI) {
            return ['k' => null, 'beta' => null, 'r2' => null, 'n' => $n, 'dapatDipakai' => false,
                    'alasan' => 'Pengukuran getaran belum cukup; tetapan umum masih dipakai.'];
        }

        ['m' => $m, 'b' => $b, 'r2' => $r2] = Regresi::lurus($titik);

        $beta = -$m;
        $k = exp($b);

        if ($beta <= 0) {
            return ['k' => null, 'beta' => null, 'r2' => round($r2, 3), 'n' => $n, 'dapatDipakai' => false,
                    'alasan' => 'Getaran terukur tidak melemah terhadap jarak — periksa pencatatan jarak dan isi per tundaan.'];
        }

        return [
            'k' => round($k, 1), 'beta' => round($beta, 3), 'r2' => round($r2, 3), 'n' => $n,
            'dapatDipakai' => $r2 >= 0.6,
            'alasan' => $r2 >= 0.6
                ? 'Tetapan situs dari '.$n.' pengukuran.'
                : 'Sebaran terlalu berserak; tetapan situs belum layak menggantikan tetapan umum.',
        ];
    }

    /* ═══════════ lemparan batu ═══════════ */

    /**
     * Radius lemparan batu maksimum secara teori (Lundborg), meter.
     *
     * L = 260 · d^(2/3), dengan d diameter lubang dalam inci.
     *
     * Angka ini jarak terjauh yang MUNGKIN dicapai serpihan pada kondisi
     * terburuk, bukan jarak yang biasa terjadi. Dipakai sebagai dasar
     * radius pengamanan justru karena sifatnya itu: yang perlu diamankan
     * adalah kemungkinan terburuknya, bukan rata-ratanya.
     *
     * Stemming yang kurang dan burden yang terlalu tipis adalah dua
     * sebab lemparan batu yang paling sering, dan keduanya diperiksa
     * terpisah lewat periksaGeometri().
     */
    public static function radiusLemparan(float $diameterLubangMm): ?float
    {
        if ($diameterLubangMm <= 0) return null;

        $inci = $diameterLubangMm / 25.4;

        return round(260 * $inci ** (2 / 3), 1);
    }

    /* ═══════════ fragmentasi ═══════════ */

    /**
     * Ukuran fragmen rata-rata (Kuz-Ram), sentimeter.
     *
     *   X50 = A · (V0/Q)^0.8 · Q^(1/6) · (115/E)^(19/30)
     *
     * A adalah faktor batuan: makin keras dan makin masif, makin besar.
     * E kekuatan bobot relatif bahan peledak terhadap ANFO (=100).
     *
     * Modelnya empiris dan meleset pada ekor sebaran — bongkah terbesar
     * justru yang paling menentukan kelancaran peremuk, dan justru itu
     * yang paling tidak dapat diandalkan dari model ini. Karena itu
     * hasilnya dipakai untuk membandingkan rancangan satu sama lain,
     * bukan untuk menjanjikan ukuran yang akan keluar.
     */
    public static function x50(
        float $volumePerLubangM3, float $isiPerLubangKg,
        float $faktorBatuan = 7.0, float $kekuatanRelatif = 100.0
    ): ?float {
        if ($volumePerLubangM3 <= 0 || $isiPerLubangKg <= 0
            || $faktorBatuan <= 0 || $kekuatanRelatif <= 0) return null;

        $x50 = $faktorBatuan
             * ($volumePerLubangM3 / $isiPerLubangKg) ** 0.8
             * $isiPerLubangKg ** (1 / 6)
             * (115 / $kekuatanRelatif) ** (19 / 30);

        return round($x50, 2);
    }

    /* ═══════════ geometri rancangan ═══════════ */

    /**
     * Periksa geometri terhadap kaidah lapangan yang lazim.
     *
     * Yang diperiksa hanya nisbah yang punya rentang baku dan dapat
     * diukur pita meter — bukan penilaian yang menuntut keahlian
     * peledakan. Tiap temuan menyebutkan akibatnya, sebab nisbah tanpa
     * akibatnya hanya angka yang tidak menggerakkan siapa pun.
     *
     * @return list<array{hal:string,nilai:float,anjuran:string,akibat:string}>
     */
    public static function periksaGeometri(
        ?float $burdenM, ?float $spasiM, ?float $stemmingM,
        ?float $diameterMm, ?float $subdrillM, ?float $tinggiJenjangM
    ): array {
        $temuan = [];

        // Stemming terlalu pendek adalah sebab lemparan batu ke atas
        // yang paling sering: gas ledakan menemukan jalan keluar
        // termudah lewat mulut lubang.
        if ($burdenM > 0 && $stemmingM !== null && $stemmingM > 0) {
            $nisbah = $stemmingM / $burdenM;
            if ($nisbah < 0.7) {
                $temuan[] = [
                    'hal' => 'Stemming terhadap burden', 'nilai' => round($nisbah, 2),
                    'anjuran' => '0,7–1,0 × burden',
                    'akibat' => 'Gas ledakan keluar lewat mulut lubang — lemparan batu ke atas dan ledakan udara.',
                ];
            }
        }

        // Burden terlalu tipis melempar batu mendatar ke arah muka bebas.
        if ($burdenM > 0 && $diameterMm > 0) {
            $nisbah = $burdenM / ($diameterMm / 1000);
            if ($nisbah < 20) {
                $temuan[] = [
                    'hal' => 'Burden terhadap diameter', 'nilai' => round($nisbah, 1),
                    'anjuran' => '25–35 × diameter lubang',
                    'akibat' => 'Burden terlalu tipis — lemparan batu mendatar ke arah muka bebas.',
                ];
            } elseif ($nisbah > 40) {
                $temuan[] = [
                    'hal' => 'Burden terhadap diameter', 'nilai' => round($nisbah, 1),
                    'anjuran' => '25–35 × diameter lubang',
                    'akibat' => 'Burden terlalu tebal — bongkah besar dan tonjolan di kaki jenjang.',
                ];
            }
        }

        if ($burdenM > 0 && $spasiM > 0) {
            $nisbah = $spasiM / $burdenM;
            if ($nisbah < 1.0 || $nisbah > 2.0) {
                $temuan[] = [
                    'hal' => 'Spasi terhadap burden', 'nilai' => round($nisbah, 2),
                    'anjuran' => '1,0–2,0 × burden',
                    'akibat' => 'Sebaran energi tidak merata — fragmentasi kasar setempat.',
                ];
            }
        }

        // Subdrill kurang meninggalkan tonjolan di lantai jenjang yang
        // harus dibongkar ulang; berlebih memecah lantai di bawahnya.
        if ($burdenM > 0 && $subdrillM !== null) {
            $nisbah = $subdrillM / $burdenM;
            if ($nisbah < 0.2) {
                $temuan[] = [
                    'hal' => 'Subdrill terhadap burden', 'nilai' => round($nisbah, 2),
                    'anjuran' => '0,2–0,4 × burden',
                    'akibat' => 'Tonjolan tertinggal di lantai jenjang dan harus dibongkar ulang.',
                ];
            }
        }

        if ($tinggiJenjangM > 0 && $burdenM > 0 && $tinggiJenjangM / $burdenM < 1.5) {
            $temuan[] = [
                'hal' => 'Tinggi jenjang terhadap burden', 'nilai' => round($tinggiJenjangM / $burdenM, 2),
                'anjuran' => 'sekurangnya 1,5 × burden',
                'akibat' => 'Jenjang terlalu pendek terhadap burden — kekang berlebih dan ledakan udara.',
            ];
        }

        return $temuan;
    }
}
