<?php

namespace App\Support;

/**
 * Mining Engineering Hub — data acuan dan hitungannya.
 *
 * Angka di sini adalah data contoh untuk keperluan rekayasa: dipakai
 * menguji rumus, melatih pembacaan indikator, dan menyiapkan format
 * laporan sebelum data lapangan yang sebenarnya masuk. Karena itu ia
 * berdiri di berkas ini, bukan di basis data — modul Energy Performance
 * yang menyimpan catatan sungguhan.
 *
 * Yang disimpan hanya besaran mentah: liter, jam, ton, BCM, jumlah
 * kejadian. Ketersediaan, intensitas, rasio, dan frekuensi kecelakaan
 * seluruhnya dihitung saat dibaca. Angka turunan yang ikut disimpan cepat
 * atau lambat berselisih dengan sumbernya, dan tidak ada cara bagi
 * pembacanya untuk tahu mana yang benar.
 */
final class Engineering
{
    /* ================= faktor konversi ================= */

    public const GJ_PER_LITER   = 0.0358;   // solar, ±35,8 MJ per liter
    public const GJ_PER_KWH     = 0.0036;   // 1 kWh = 3,6 MJ, tepat menurut definisinya
    public const TCO2E_PER_L    = 0.00268;
    public const TCO2E_PER_KWH  = 0.00087;
    public const RP_PER_LITER   = 14_500;
    public const RP_PER_KWH     = 1_450;

    /** Nilai ambang batas kebisingan, Permenaker No. 5 Tahun 2018. */
    public const NAB_DBA        = 85;
    public const LAJU_TUKAR_DB  = 3;        // ISO 1999; setiap 3 dB, durasi izin separuh

    public const SITUS   = 'Site Sangatta Utara';
    public const PERIODE = 'Agustus 2026';

    /* ================= data ================= */

    /** Produksi harian: ton batu bara ROM dan BCM overburden. */
    public static function produksi(): array
    {
        return [
            ['tgl' => '2026-08-05', 'ton' => 11840, 'bcm' => 92500,  'target' => 12000],
            ['tgl' => '2026-08-06', 'ton' => 12310, 'bcm' => 96800,  'target' => 12000],
            ['tgl' => '2026-08-07', 'ton' => 12680, 'bcm' => 99400,  'target' => 12000],
            ['tgl' => '2026-08-08', 'ton' => 11520, 'bcm' => 88300,  'target' => 12000],
            ['tgl' => '2026-08-09', 'ton' => 12940, 'bcm' => 101200, 'target' => 12000],
            ['tgl' => '2026-08-10', 'ton' => 13180, 'bcm' => 103900, 'target' => 12000],
            ['tgl' => '2026-08-11', 'ton' => 12450, 'bcm' => 97600,  'target' => 12000],
        ];
    }

    public static function produksiBulanan(): array
    {
        return [
            ['label' => 'Jun 2026', 'ton' => 348200, 'target' => 360000],
            ['label' => 'Jul 2026', 'ton' => 366900, 'target' => 360000],
            ['label' => 'Agu 2026', 'ton' => 372100, 'target' => 360000],
        ];
    }

    /** Pemakaian energi harian, pada satuan asalnya. */
    public static function energi(): array
    {
        return [
            ['tgl' => '2026-08-05', 'liter' => 14980, 'kwh' => 21400],
            ['tgl' => '2026-08-06', 'liter' => 15320, 'kwh' => 21900],
            ['tgl' => '2026-08-07', 'liter' => 15610, 'kwh' => 22350],
            ['tgl' => '2026-08-08', 'liter' => 14730, 'kwh' => 20800],
            ['tgl' => '2026-08-09', 'liter' => 15840, 'kwh' => 22600],
            ['tgl' => '2026-08-10', 'liter' => 16020, 'kwh' => 23100],
            ['tgl' => '2026-08-11', 'liter' => 15380, 'kwh' => 22050],
        ];
    }

    public const BASELINE_GJ_TON = 0.0472;   // realisasi tahun lalu
    public const TARGET_GJ_TON   = 0.0425;   // sasaran tahun berjalan
    public const TERBARUKAN_KWH  = 1860;     // PLTS site, per hari

    public static function programHemat(): array
    {
        return [
            ['judul' => 'Pembatasan idle dump truck di area loading', 'liter' => 4200, 'kwh' => 0,    'status' => 'Berjalan'],
            ['judul' => 'Penggantian lampu sorot workshop ke LED',    'liter' => 0,    'kwh' => 3800, 'status' => 'Selesai'],
            ['judul' => 'Penjadwalan ulang pompa dewatering',          'liter' => 0,    'kwh' => 5200, 'status' => 'Disetujui'],
            ['judul' => 'Perataan ulang jalan angkut segmen 3',        'liter' => 3100, 'kwh' => 0,    'status' => 'Kajian'],
        ];
    }

    /**
     * Armada.
     *
     * jam_kerja + jam_standby + jam_rusak = jam terjadwal, dan seluruh
     * angka ketersediaan diturunkan dari ketiganya — tidak satu pun
     * diketik terpisah.
     */
    public static function armada(): array
    {
        return [
            ['kode' => 'DT-001', 'tipe' => 'HD785-7',     'kelas' => 'Hauling',   'status' => 'Operating',   'kerja' => 604, 'standby' => 54,  'rusak' => 62,  'liter' => 23180, 'hm' => 8421,  'payload' => 91],
            ['kode' => 'DT-002', 'tipe' => 'HD785-7',     'kelas' => 'Hauling',   'status' => 'Operating',   'kerja' => 588, 'standby' => 62,  'rusak' => 70,  'liter' => 23520, 'hm' => 7821,  'payload' => 91],
            ['kode' => 'DT-003', 'tipe' => 'HD465-7',     'kelas' => 'Hauling',   'status' => 'Standby',     'kerja' => 512, 'standby' => 148, 'rusak' => 60,  'liter' => 18430, 'hm' => 9140,  'payload' => 55],
            ['kode' => 'DT-004', 'tipe' => 'HD785-7',     'kelas' => 'Hauling',   'status' => 'Operating',   'kerja' => 596, 'standby' => 48,  'rusak' => 76,  'liter' => 24080, 'hm' => 6980,  'payload' => 91],
            ['kode' => 'DT-005', 'tipe' => 'HD465-7',     'kelas' => 'Hauling',   'status' => 'Breakdown',   'kerja' => 402, 'standby' => 88,  'rusak' => 230, 'liter' => 14760, 'hm' => 10240, 'payload' => 55],
            ['kode' => 'EX-001', 'tipe' => 'PC2000-8',    'kelas' => 'Excavator', 'status' => 'Maintenance', 'kerja' => 548, 'standby' => 62,  'rusak' => 110, 'liter' => 34240, 'hm' => 12231, 'payload' => null],
            ['kode' => 'EX-002', 'tipe' => 'PC1250-8',    'kelas' => 'Excavator', 'status' => 'Operating',   'kerja' => 612, 'standby' => 46,  'rusak' => 62,  'liter' => 32180, 'hm' => 9860,  'payload' => null],
            ['kode' => 'DZ-001', 'tipe' => 'D375A-6',     'kelas' => 'Dozer',     'status' => 'Operating',   'kerja' => 574, 'standby' => 78,  'rusak' => 68,  'liter' => 26840, 'hm' => 11420, 'payload' => null],
            ['kode' => 'DZ-002', 'tipe' => 'D155A-6',     'kelas' => 'Dozer',     'status' => 'Operating',   'kerja' => 561, 'standby' => 84,  'rusak' => 75,  'liter' => 22310, 'hm' => 8730,  'payload' => null],
            ['kode' => 'GD-001', 'tipe' => 'GD825A-2',    'kelas' => 'Support',   'status' => 'Operating',   'kerja' => 486, 'standby' => 168, 'rusak' => 66,  'liter' => 9820,  'hm' => 7410,  'payload' => null],
            ['kode' => 'WT-001', 'tipe' => 'Water Truck', 'kelas' => 'Support',   'status' => 'Operating',   'kerja' => 502, 'standby' => 152, 'rusak' => 66,  'liter' => 10240, 'hm' => 6320,  'payload' => null],
            ['kode' => 'FT-001', 'tipe' => 'Fuel Truck',  'kelas' => 'Support',   'status' => 'Standby',     'kerja' => 398, 'standby' => 256, 'rusak' => 66,  'liter' => 7640,  'hm' => 5180,  'payload' => null],
        ];
    }

    public const PRODUKTIVITAS = [
        'cycle_menit'   => 24.6,
        'jarak_km'      => 3.4,
        'truck_factor'  => 0.94,
        'kecepatan_kmh' => 18.2,
        'match_factor'  => 0.87,
    ];

    public const PEMELIHARAAN = [
        'pm_compliance' => 91.4,
        'mtbf_jam'      => 168.5,
        'mttr_jam'      => 6.8,
        'breakdown'     => 14,
        'biaya_rp'      => 3_480_000_000,
    ];

    public static function pemeliharaanBulanan(): array
    {
        return [
            ['label' => 'Mar', 'preventif' => 820,  'korektif' => 410, 'breakdown' => 260],
            ['label' => 'Apr', 'preventif' => 860,  'korektif' => 380, 'breakdown' => 240],
            ['label' => 'Mei', 'preventif' => 910,  'korektif' => 350, 'breakdown' => 210],
            ['label' => 'Jun', 'preventif' => 940,  'korektif' => 330, 'breakdown' => 190],
            ['label' => 'Jul', 'preventif' => 980,  'korektif' => 300, 'breakdown' => 170],
            ['label' => 'Agu', 'preventif' => 1010, 'korektif' => 280, 'breakdown' => 150],
        ];
    }

    public static function pekerjaan(): array
    {
        return [
            ['unit' => 'DT-005', 'masalah' => 'Kebocoran final drive kiri',         'prioritas' => 'Critical', 'status' => 'In Progress', 'pic' => 'Tim Plant A', 'tanggal' => '2026-08-09'],
            ['unit' => 'EX-001', 'masalah' => 'Penggantian bucket tooth & adapter', 'prioritas' => 'High',     'status' => 'In Progress', 'pic' => 'Tim Plant B', 'tanggal' => '2026-08-10'],
            ['unit' => 'DT-002', 'masalah' => 'Getaran tidak wajar pada propeller', 'prioritas' => 'High',     'status' => 'Open',        'pic' => 'Tim Plant A', 'tanggal' => '2026-08-11'],
            ['unit' => 'DZ-002', 'masalah' => 'PM 500 jam',                          'prioritas' => 'Medium',   'status' => 'Scheduled',   'pic' => 'Tim PM',      'tanggal' => '2026-08-13'],
            ['unit' => 'GD-001', 'masalah' => 'Kalibrasi blade control',             'prioritas' => 'Low',      'status' => 'Scheduled',   'pic' => 'Tim PM',      'tanggal' => '2026-08-15'],
            ['unit' => 'DT-003', 'masalah' => 'Penggantian ban posisi 4',            'prioritas' => 'Medium',   'status' => 'Closed',      'pic' => 'Tim Tyre',    'tanggal' => '2026-08-07'],
            ['unit' => 'EX-002', 'masalah' => 'Perbaikan seal boom cylinder',        'prioritas' => 'High',     'status' => 'Closed',      'pic' => 'Tim Plant B', 'tanggal' => '2026-08-06'],
        ];
    }

    /** Keselamatan: jumlah kejadian dan jam kerja, bukan frekuensi jadi. */
    public const HSE = [
        'jam_kerja'   => 4_820_000,
        'pengali'     => 1_000_000,      // per satu juta jam kerja
        'recordable'  => 6,
        'lost_time'   => 2,
        'hari_hilang' => 84,
        'near_miss'   => 148,
        'observasi'   => 1264,
        'hari_tanpa_lti' => 96,
    ];

    /** Penerapan tujuh elemen SMKP, Kepdirjen 185.K/37.04/DJB/2019. */
    public static function smkp(): array
    {
        return [
            ['no' => 1, 'elemen' => 'Kebijakan',                                'bobot' => 10, 'capaian' => 96],
            ['no' => 2, 'elemen' => 'Perencanaan',                              'bobot' => 15, 'capaian' => 88],
            ['no' => 3, 'elemen' => 'Organisasi dan Personel',                  'bobot' => 17, 'capaian' => 84],
            ['no' => 4, 'elemen' => 'Implementasi',                             'bobot' => 35, 'capaian' => 79],
            ['no' => 5, 'elemen' => 'Pemantauan, Evaluasi dan Tindak Lanjut',   'bobot' => 15, 'capaian' => 82],
            ['no' => 6, 'elemen' => 'Dokumentasi',                              'bobot' => 3,  'capaian' => 91],
            ['no' => 7, 'elemen' => 'Tinjauan Manajemen & Peningkatan Kinerja', 'bobot' => 5,  'capaian' => 74],
        ];
    }

    /**
     * Acuan regulasi dan standar.
     *
     * Hanya identitas dokumen yang dicatat. Isi pasalnya sengaja tidak
     * disalin: teks standar ISO berhak cipta, dan regulasi harus dibaca
     * dari sumber resminya supaya revisinya tidak tertinggal.
     */
    public static function regulasi(): array
    {
        return [
            ['judul' => 'Kepdirjen Minerba 185.K/37.04/DJB/2019', 'kategori' => 'SMKP', 'tahun' => 2019,
             'penerbit' => 'Ditjen Mineral dan Batubara', 'sumber' => 'https://jdih.esdm.go.id',
             'ket' => 'Petunjuk teknis penerapan SMKP Minerba beserta tujuh elemen dan tata cara penilaiannya.'],
            ['judul' => 'Permen ESDM No. 26 Tahun 2018', 'kategori' => 'Pertambangan', 'tahun' => 2018,
             'penerbit' => 'Kementerian ESDM', 'sumber' => 'https://jdih.esdm.go.id',
             'ket' => 'Pelaksanaan kaidah pertambangan yang baik dan pengawasan pertambangan mineral dan batubara.'],
            ['judul' => 'Kepmen ESDM No. 1827 K/30/MEM/2018', 'kategori' => 'Pertambangan', 'tahun' => 2018,
             'penerbit' => 'Kementerian ESDM', 'sumber' => 'https://jdih.esdm.go.id',
             'ket' => 'Pedoman pelaksanaan kaidah teknik pertambangan yang baik beserta lampiran keselamatannya.'],
            ['judul' => 'Permenaker No. 5 Tahun 2018', 'kategori' => 'K3', 'tahun' => 2018,
             'penerbit' => 'Kementerian Ketenagakerjaan', 'sumber' => 'https://jdih.kemnaker.go.id',
             'ket' => 'K3 lingkungan kerja; memuat nilai ambang batas kebisingan dan faktor fisika lainnya.'],
            ['judul' => 'SNI ISO 45001:2018', 'kategori' => 'K3', 'tahun' => 2018,
             'penerbit' => 'Badan Standardisasi Nasional', 'sumber' => 'https://akses-sni.bsn.go.id',
             'ket' => 'Sistem manajemen keselamatan dan kesehatan kerja — persyaratan dengan panduan penggunaan.'],
            ['judul' => 'SNI ISO 14001:2015', 'kategori' => 'Lingkungan', 'tahun' => 2015,
             'penerbit' => 'Badan Standardisasi Nasional', 'sumber' => 'https://akses-sni.bsn.go.id',
             'ket' => 'Sistem manajemen lingkungan — persyaratan dengan panduan penggunaan.'],
            ['judul' => 'SNI ISO 50001:2018', 'kategori' => 'Energi', 'tahun' => 2018,
             'penerbit' => 'Badan Standardisasi Nasional', 'sumber' => 'https://akses-sni.bsn.go.id',
             'ket' => 'Sistem manajemen energi — persyaratan dengan panduan penggunaan.'],
            ['judul' => 'SNI ISO 9001:2015', 'kategori' => 'Mutu', 'tahun' => 2015,
             'penerbit' => 'Badan Standardisasi Nasional', 'sumber' => 'https://akses-sni.bsn.go.id',
             'ket' => 'Sistem manajemen mutu — persyaratan.'],
            ['judul' => 'ISO 1999:2013', 'kategori' => 'K3', 'tahun' => 2013,
             'penerbit' => 'International Organization for Standardization', 'sumber' => 'https://www.iso.org',
             'ket' => 'Penaksiran pajanan bising di tempat kerja dan perkiraan gangguan pendengaran akibat bising.'],
        ];
    }

    /* ================= hitungan ================= */

    /** Pembagian yang tidak pernah menghasilkan tak hingga. */
    public static function bagi(float $a, float $b): float
    {
        return $b > 0 ? $a / $b : 0.0;
    }

    public static function jamTerjadwal(array $u): float { return $u['kerja'] + $u['standby'] + $u['rusak']; }

    /** Physical Availability — alat siap dipakai, entah dipakai atau tidak. */
    public static function pa(array $u): float { return self::bagi($u['kerja'] + $u['standby'], self::jamTerjadwal($u)) * 100; }

    /** Mechanical Availability — kesiapan dari sisi mesin saja. */
    public static function ma(array $u): float { return self::bagi($u['kerja'], $u['kerja'] + $u['rusak']) * 100; }

    /** Use of Availability — seberapa banyak kesiapan itu benar dipakai. */
    public static function ua(array $u): float { return self::bagi($u['kerja'], $u['kerja'] + $u['standby']) * 100; }

    public static function utilisasi(array $u): float { return self::bagi($u['kerja'], self::jamTerjadwal($u)) * 100; }

    public static function fuelRate(array $u): float { return self::bagi($u['liter'], $u['kerja']); }

    public static function ringkasArmada(): array
    {
        $unit = self::armada();
        $kerja = $standby = $rusak = $liter = 0.0;
        foreach ($unit as $u) {
            $kerja += $u['kerja']; $standby += $u['standby'];
            $rusak += $u['rusak']; $liter += $u['liter'];
        }
        $terjadwal = $kerja + $standby + $rusak;

        return [
            'kerja' => $kerja, 'standby' => $standby, 'rusak' => $rusak,
            'liter' => $liter, 'terjadwal' => $terjadwal,
            'jumlah' => count($unit),
            'beroperasi' => count(array_filter($unit, fn ($u) => $u['status'] === 'Operating')),
            'pa' => self::bagi($kerja + $standby, $terjadwal) * 100,
            'ma' => self::bagi($kerja, $kerja + $rusak) * 100,
            'ua' => self::bagi($kerja, $kerja + $standby) * 100,
            'utilisasi' => self::bagi($kerja, $terjadwal) * 100,
            'fuelRate' => self::bagi($liter, $kerja),
        ];
    }

    /** Rata-rata liter per jam tiap kelas — acuan status tiap unit. */
    public static function acuanKelas(): array
    {
        $out = [];
        foreach (self::armada() as $u) {
            $out[$u['kelas']]['liter'] = ($out[$u['kelas']]['liter'] ?? 0) + $u['liter'];
            $out[$u['kelas']]['kerja'] = ($out[$u['kelas']]['kerja'] ?? 0) + $u['kerja'];
        }
        return array_map(fn ($k) => self::bagi($k['liter'], $k['kerja']), $out);
    }

    /**
     * Status keborosan sebuah unit terhadap acuan kelasnya sendiri.
     *
     * Ambangnya relatif, bukan mutlak: excavator dan dump truck memang
     * berbeda haus, dan mengurutkan liter mentah akan selalu menempatkan
     * alat bertenaga besar di puncak daftar boros — yang tidak memberi
     * tahu apa pun.
     */
    public static function statusBoros(float $nilai, float $acuan): array
    {
        if ($acuan <= 0) return ['kode' => 'belum', 'label' => 'Belum ada acuan', 'warna' => '#9AA3AE'];
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

    public static function ringkasProduksi(): array
    {
        $h = self::produksi();
        $ton = array_sum(array_column($h, 'ton'));
        $bcm = array_sum(array_column($h, 'bcm'));
        $target = array_sum(array_column($h, 'target'));

        return [
            'harian' => $h, 'hari' => count($h),
            'ton' => $ton, 'bcm' => $bcm, 'target' => $target,
            'tonHari' => $ton / count($h),
            'terakhir' => $h[count($h) - 1],
            'capaian' => self::bagi($ton, $target) * 100,
            'sr' => self::bagi($bcm, $ton),
        ];
    }

    /** Perubahan hari terakhir terhadap rata-rata hari sebelumnya. */
    public static function trenProduksi(): float
    {
        $h = self::produksi();
        if (count($h) < 2) return 0.0;
        $sebelum = array_sum(array_column(array_slice($h, 0, -1), 'ton')) / (count($h) - 1);
        return self::bagi($h[count($h) - 1]['ton'] - $sebelum, $sebelum) * 100;
    }

    public static function ringkasEnergi(): array
    {
        $prod = collect(self::produksi())->keyBy('tgl');
        $deret = [];
        $gj = $liter = $kwh = $tco2e = $rupiah = $ton = 0.0;

        foreach (self::energi() as $e) {
            $g = $e['liter'] * self::GJ_PER_LITER + $e['kwh'] * self::GJ_PER_KWH;
            $t = (float) ($prod[$e['tgl']]['ton'] ?? 0);

            $deret[] = [
                'tgl' => $e['tgl'], 'liter' => $e['liter'], 'kwh' => $e['kwh'],
                'gj' => $g, 'ton' => $t, 'intensitas' => self::bagi($g, $t),
            ];

            $gj += $g; $liter += $e['liter']; $kwh += $e['kwh']; $ton += $t;
            $tco2e  += $e['liter'] * self::TCO2E_PER_L + $e['kwh'] * self::TCO2E_PER_KWH;
            $rupiah += $e['liter'] * self::RP_PER_LITER + $e['kwh'] * self::RP_PER_KWH;
        }

        $n = max(1, count($deret));
        $intensitas = self::bagi($gj, $ton);

        return [
            'deret' => $deret, 'hari' => $n,
            'gj' => $gj, 'liter' => $liter, 'kwh' => $kwh,
            'tco2e' => $tco2e, 'rupiah' => $rupiah, 'ton' => $ton,
            'gjHari' => $gj / $n, 'literHari' => $liter / $n, 'kwhHari' => $kwh / $n,
            'intensitas' => $intensitas,
            'fuelRatio' => self::bagi($liter, $ton),
            'baseline' => self::BASELINE_GJ_TON,
            'target' => self::TARGET_GJ_TON,
            'penurunan' => self::bagi(self::BASELINE_GJ_TON - $intensitas, self::BASELINE_GJ_TON) * 100,
            'terbarukanPersen' => self::bagi(self::TERBARUKAN_KWH, $kwh / $n) * 100,
        ];
    }

    /** Nilai sebuah program penghematan per bulan, dari satuan asalnya. */
    public static function nilaiProgram(array $p): array
    {
        return [
            'gj'     => $p['liter'] * self::GJ_PER_LITER + $p['kwh'] * self::GJ_PER_KWH,
            'tco2e'  => $p['liter'] * self::TCO2E_PER_L  + $p['kwh'] * self::TCO2E_PER_KWH,
            'rupiah' => $p['liter'] * self::RP_PER_LITER + $p['kwh'] * self::RP_PER_KWH,
        ];
    }

    public static function ringkasHse(): array
    {
        $h = self::HSE;
        $bobot = array_sum(array_column(self::smkp(), 'bobot'));
        $nilai = 0.0;
        foreach (self::smkp() as $e) $nilai += $e['capaian'] * $e['bobot'];

        return [
            'trifr'    => self::bagi($h['recordable'], $h['jam_kerja']) * $h['pengali'],
            'ltifr'    => self::bagi($h['lost_time'], $h['jam_kerja']) * $h['pengali'],
            'severity' => self::bagi($h['hari_hilang'], $h['jam_kerja']) * $h['pengali'],
            'nilaiSmkp' => self::bagi($nilai, $bobot),
        ] + $h;
    }

    public static function ringkasPemeliharaan(): array
    {
        $b = self::pemeliharaanBulanan();
        $ini = $b[count($b) - 1];
        $total = $ini['preventif'] + $ini['korektif'] + $ini['breakdown'];
        $kerja = self::pekerjaan();

        return self::PEMELIHARAAN + [
            'bulanan' => $b, 'bulanIni' => $ini, 'totalJam' => $total,
            'porsiPreventif' => self::bagi($ini['preventif'], $total) * 100,
            'terbuka' => count(array_filter($kerja, fn ($p) => $p['status'] !== 'Closed')),
            'kritis'  => count(array_filter($kerja, fn ($p) => $p['prioritas'] === 'Critical' && $p['status'] !== 'Closed')),
        ];
    }

    /* ================= pajanan bising ================= */

    /**
     * Dosis dan TWA pajanan bising.
     *
     * Kriteria 85 dBA untuk 8 jam dengan laju pertukaran 3 dB — dasar yang
     * dipakai Permenaker No. 5 Tahun 2018 dan ISO 1999.
     *
     *   Tᵢ    = 8 ÷ 2^((Lᵢ − 85) ÷ 3)
     *   Dosis = 100 × Σ (Cᵢ ÷ Tᵢ)
     *   TWA   = 85 + 3 × log₂(Dosis ÷ 100)
     *
     * Beberapa baris pajanan dijumlahkan dosisnya, bukan dirata-rata
     * tingkatnya: satu jam pada 100 dBA jauh lebih berat daripada delapan
     * jam pada 86 dBA, dan perataan biasa menyembunyikan itu.
     *
     * @param  array<array{db:float,jam:float}>  $pajanan
     * @return array{dosis:float,twa:?float,jam:float,lewat:bool}
     */
    public static function bising(array $pajanan): array
    {
        $dosis = 0.0; $jam = 0.0;

        foreach ($pajanan as $p) {
            $db = (float) ($p['db'] ?? 0);
            $c  = (float) ($p['jam'] ?? 0);
            if ($c <= 0 || $db <= 0) { $jam += max(0, $c); continue; }

            $jam += $c;
            $t = 8 / (2 ** (($db - self::NAB_DBA) / self::LAJU_TUKAR_DB));
            $dosis += $c / $t;
        }

        $dosis *= 100;

        return [
            'dosis' => $dosis,
            'twa'   => $dosis > 0 ? self::NAB_DBA + self::LAJU_TUKAR_DB * (log($dosis / 100) / log(2)) : null,
            'jam'   => $jam,
            'lewat' => $dosis > 100,
        ];
    }
}
