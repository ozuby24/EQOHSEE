<?php

namespace App\Support;

use App\Support\Energi\{Kalkulator, RegistriFaktor};

/**
 * Kinerja energi tambang — faktor konversi, kategori, dan hitungannya.
 *
 * Seluruh sumber energi dibawa ke satuan yang sama, gigajoule, sebelum
 * dijumlahkan. Tanpa itu, liter solar dan kilowatt-jam tidak dapat
 * dibandingkan apalagi ditotal — dan intensitas energi kehilangan artinya.
 *
 * Angka konversi diletakkan di satu tempat: faktor emisi dan harga bahan
 * bakar berubah dari tahun ke tahun, dan yang berubah harus satu berkas,
 * bukan belasan pemanggilan yang tersebar.
 *
 * SEJAK MODUL ENERGI & KARBON (FSD §14 M5): fungsi konversi di bawah
 * membaca registri faktor berversi lewat App\Support\Energi\Kalkulator —
 * NCV & densitas, campuran FAME yang berlaku, EF CO₂/CH₄/N₂O, GWP, faktor
 * grid, dan harga. Konstanta di bawah tinggal sebagai cadangan WARISAN
 * yang hanya dipakai bila paket faktor belum pernah dipasang
 * (`php artisan energi:pasang`) — pada lingkungan uji, atau pemasangan
 * yang belum dideploy ulang. Mesin inventarisasi GRK tidak memakai
 * cadangan ini sama sekali: di sana faktor yang tidak ada berarti "tidak
 * dapat dihitung", bukan angka lama.
 */
final class Energi
{
    /* ================= faktor konversi ================= */

    /** Solar: 1 liter ≈ 35,8 MJ. */
    public const GJ_PER_LITER = 0.0358;

    /** Solar: 2,68 kg CO₂e per liter terbakar. */
    public const TCO2E_PER_LITER = 0.00268;

    public const RP_PER_LITER = 14_500;

    /** Listrik: 1 kWh = 3,6 MJ, tepat menurut definisinya. */
    public const GJ_PER_KWH = 0.0036;

    /** Faktor emisi jaringan listrik Indonesia, ±0,87 kg CO₂e per kWh. */
    public const TCO2E_PER_KWH = 0.00087;

    public const RP_PER_KWH = 1_450;

    /** Gas alam: ±37,3 MJ per meter kubik. */
    public const GJ_PER_M3_GAS = 0.0373;

    public const TCO2E_PER_M3_GAS = 0.00202;

    public const RP_PER_M3_GAS = 6_500;

    /* ================= kategori ================= */

    /** Kelompok alat, dipakai menu Equipment Performance. */
    public const KATEGORI = [
        'hauling'   => 'Hauling',
        'excavator' => 'Excavator',
        'dozer'     => 'Dozer',
        'support'   => 'Support Equipment',
    ];

    /** Area pemakaian listrik. */
    public const AREA = [
        'camp'       => 'Camp',
        'workshop'   => 'Workshop',
        'office'     => 'Office',
        'crusher'    => 'Crusher',
        'plant'      => 'Processing Plant',
        'pump'       => 'Pump',
        'lighting'   => 'Lighting',
    ];

    /* PLN dan IUPTLS adalah listrik yang DIBELI (Scope 2); genset dihitung
       dari solarnya (Scope 1); PLTS di lokasi adalah EBT tanpa emisi. */
    public const SUMBER_LISTRIK = ['pln' => 'PLN', 'genset' => 'Genset', 'iuptls' => 'IUPTLS / pihak lain', 'plts' => 'PLTS'];

    /** Status peluang penghematan, dari usulan sampai terwujud. */
    public const STATUS_PELUANG = ['usulan', 'review', 'disetujui', 'berjalan', 'selesai', 'ditolak'];

    /* ================= konversi ================= */

    /**
     * Hasil mesin perhitungan untuk satu kuantitas, atau null bila paket
     * faktor belum terpasang. Konteks perusahaannya perusahaan pengguna —
     * fasad ini melayani tampilan operasional perusahaan sendiri.
     */
    private static function hasil(string $kode, float $jumlah, $tanggal = null): ?array
    {
        if ($jumlah == 0.0 || !RegistriFaktor::terpasang()) return null;

        return Kalkulator::hitung($kode, $jumlah, $tanggal ?? now(), [
            'company_id' => auth()->user()?->company_id,
        ]);
    }

    public static function literKeGj(float $liter, $tanggal = null): float
    {
        return self::hasil('solar', $liter, $tanggal)['gj'] ?? $liter * self::GJ_PER_LITER;
    }

    /** Definisi fisika (1 kWh = 3,6 MJ) — bukan faktor yang dapat berubah. */
    public static function kwhKeGj(float $kwh): float       { return $kwh * self::GJ_PER_KWH; }
    public static function gjKeKwh(float $gj): float        { return self::GJ_PER_KWH > 0 ? $gj / self::GJ_PER_KWH : 0.0; }

    public static function m3KeGj(float $m3, $tanggal = null): float
    {
        return self::hasil('gas', $m3, $tanggal)['gj'] ?? $m3 * self::GJ_PER_M3_GAS;
    }

    /** tCO₂e FOSIL; CO₂ biogenik dari FAME tidak ikut (dilaporkan terpisah). */
    public static function literKeCo2(float $liter, $tanggal = null): float
    {
        return self::hasil('solar', $liter, $tanggal)['co2e_t'] ?? $liter * self::TCO2E_PER_LITER;
    }

    public static function kwhKeCo2(float $kwh, $tanggal = null): float
    {
        return self::hasil('listrik_pln', $kwh, $tanggal)['co2e_t'] ?? $kwh * self::TCO2E_PER_KWH;
    }

    public static function m3KeCo2(float $m3, $tanggal = null): float
    {
        return self::hasil('gas', $m3, $tanggal)['co2e_t'] ?? $m3 * self::TCO2E_PER_M3_GAS;
    }

    public static function literKeRp(float $liter, $tanggal = null): float
    {
        return self::hasil('solar', $liter, $tanggal)['rupiah'] ?? $liter * self::RP_PER_LITER;
    }

    public static function kwhKeRp(float $kwh, $tanggal = null): float
    {
        return self::hasil('listrik_pln', $kwh, $tanggal)['rupiah'] ?? $kwh * self::RP_PER_KWH;
    }

    public static function m3KeRp(float $m3, $tanggal = null): float
    {
        return self::hasil('gas', $m3, $tanggal)['rupiah'] ?? $m3 * self::RP_PER_M3_GAS;
    }

    /* ================= hitungan kinerja ================= */

    /**
     * Intensitas energi: gigajoule per ton produksi.
     *
     * Produksi nol mengembalikan nol, bukan pembagian tak hingga — periode
     * tanpa produksi memang tidak punya intensitas, dan menampilkannya
     * sebagai angka raksasa hanya menyesatkan pembacanya.
     */
    public static function intensitas(float $totalGj, float $ton): float
    {
        return $ton > 0 ? $totalGj / $ton : 0.0;
    }

    /** Rasio bahan bakar: liter per ton (atau per BCM bila itu pembaginya). */
    public static function rasio(float $liter, float $pembagi): float
    {
        return $pembagi > 0 ? $liter / $pembagi : 0.0;
    }

    /**
     * Penurunan terhadap garis dasar, dalam persen.
     * Positif berarti membaik — pemakaian energi turun.
     */
    public static function penurunan(float $baseline, float $sekarang): float
    {
        return $baseline > 0 ? (($baseline - $sekarang) / $baseline) * 100 : 0.0;
    }

    /**
     * Status efisiensi sebuah unit terhadap acuan kategorinya.
     *
     * Ambangnya relatif, bukan angka mutlak: excavator dan dump truck
     * memang berbeda haus, jadi yang dibandingkan adalah selisihnya
     * terhadap rata-rata kelompoknya sendiri.
     *
     * @return array{kode:string,label:string,warna:string}
     */
    public static function statusEfisiensi(float $nilai, float $acuan): array
    {
        if ($acuan <= 0) {
            return ['kode' => 'belum', 'label' => 'Belum ada acuan', 'warna' => '#9AA3AE'];
        }

        $rasio = $nilai / $acuan;

        return match (true) {
            /* Labelnya berbahasa Indonesia seperti seluruh aplikasi.
               "High Consumption" di tengah halaman berbahasa Indonesia
               dibaca sebagai istilah teknis yang punya arti khusus,
               padahal ia hanya berarti boros. */
            $rasio <= 1.05 => ['kode' => 'efisien', 'label' => 'Efisien', 'warna' => '#0F766E'],
            $rasio <= 1.20 => ['kode' => 'pantau',  'label' => 'Pantau',  'warna' => '#D9993A'],
            default        => ['kode' => 'boros',   'label' => 'Boros',   'warna' => '#E2663A'],
        };
    }

    /**
     * Faktor beban listrik: rata-rata terhadap beban puncak.
     *
     * Faktor rendah berarti kapasitas terpasang jauh melebihi pemakaian
     * biasanya — biaya langganan terbayar tanpa terpakai.
     */
    public static function faktorBeban(float $kwh, float $puncakKw, float $jam): float
    {
        return ($puncakKw > 0 && $jam > 0) ? ($kwh / ($puncakKw * $jam)) * 100 : 0.0;
    }

    /** Efisiensi genset: kWh yang dihasilkan tiap liter solar. */
    public static function efisiensiGenset(float $kwh, float $liter): float
    {
        return $liter > 0 ? $kwh / $liter : 0.0;
    }

    /**
     * Selisih rekonsiliasi bahan bakar, dalam persen terhadap penyaluran.
     *
     * Selisih yang konsisten menandakan kebocoran, kesalahan ukur, atau
     * pencatatan yang tidak tertib — ketiganya perlu ditelusuri.
     */
    public static function selisihRekonsiliasi(float $disalurkan, float $tercatat): float
    {
        return $disalurkan > 0 ? (($disalurkan - $tercatat) / $disalurkan) * 100 : 0.0;
    }

    /* ================= ringkasan ================= */

    /**
     * Konsolidasi seluruh sumber energi ke satu satuan.
     *
     * @return array{gj:float,tco2e:float,rupiah:float,rincian:array<string,array<string,float>>}
     */
    public static function konsolidasi(float $liter, float $kwh, float $m3 = 0): array
    {
        $rincian = [
            'Solar'   => ['jumlah' => $liter, 'satuan' => 'L',   'gj' => self::literKeGj($liter), 'tco2e' => self::literKeCo2($liter), 'rupiah' => self::literKeRp($liter)],
            'Listrik' => ['jumlah' => $kwh,   'satuan' => 'kWh', 'gj' => self::kwhKeGj($kwh),     'tco2e' => self::kwhKeCo2($kwh),     'rupiah' => self::kwhKeRp($kwh)],
            'Gas'     => ['jumlah' => $m3,    'satuan' => 'm³',  'gj' => self::m3KeGj($m3),       'tco2e' => self::m3KeCo2($m3),       'rupiah' => self::m3KeRp($m3)],
        ];

        return [
            'gj'      => array_sum(array_column($rincian, 'gj')),
            'tco2e'   => array_sum(array_column($rincian, 'tco2e')),
            'rupiah'  => array_sum(array_column($rincian, 'rupiah')),
            'rincian' => $rincian,
        ];
    }

    /** Angka besar dipendekkan agar kartu KPI tetap terbaca. */
    public static function ringkas(float $n, int $desimal = 1): string
    {
        return match (true) {
            abs($n) >= 1e12 => number_format($n / 1e12, $desimal).'T',
            abs($n) >= 1e9  => number_format($n / 1e9,  $desimal).'B',
            abs($n) >= 1e6  => number_format($n / 1e6,  $desimal).'M',
            abs($n) >= 1e3  => number_format($n / 1e3,  $desimal).'K',
            default         => number_format($n, $desimal),
        };
    }
}
