<?php

namespace App\Support\Perancah;

/**
 * Acuan teknis modul Scaffolding.
 *
 * Seluruh angka ambang ada di satu tempat ini supaya kalkulator, kajian
 * kelayakan, form, dan uji membaca sumber yang sama. Acuannya OSHA 29
 * CFR 1926 Subpart L, BS EN 12811-1, dan Permenaker 01/MEN/1980 tentang
 * K3 konstruksi bangunan; kapasitas tiang adalah nilai tipikal pabrikan
 * dan harus dicocokkan dengan lembar data sistem yang benar-benar
 * dipakai di lapangan.
 */
final class Katalog
{
    /** Hari berlakunya satu inspeksi sebelum wajib diperiksa ulang. */
    public const MASA_BERLAKU_HARI = 7;

    /** Faktor keamanan minimum struktur (OSHA 1926.451(a)(1): 4:1). */
    public const SF_MIN = 4.0;

    /** Luas satu sole board standar 0,30 × 0,40 m. */
    public const LUAS_SOLE_M2 = 0.12;

    /** Tinggi satu lift dan bobot komponen untuk beban mati. */
    public const TINGGI_LIFT_M = 2.0;
    public const BERAT_PIPA_KG_M = 4.37;   // pipa baja galvanis Ø48,3 mm
    public const BERAT_PLANK_KG = 14.0;
    public const BERAT_COUPLER_KG = 1.1;
    public const BERAT_BASE_KG = 4.5;

    /**
     * Sistem perancah: kapasitas tiang (kN), batas H/B tanpa ikatan,
     * panjang bay standar (m).
     */
    public const JENIS = [
        'tube_coupler' => ['label' => 'Tube & coupler (pipa Ø48,3 mm)', 'kapasitas_kn' => 48.0, 'hb_maks' => 4.0, 'bay_m' => 1.8],
        'ringlock'     => ['label' => 'Ringlock / modular',             'kapasitas_kn' => 52.0, 'hb_maks' => 4.0, 'bay_m' => 2.0],
        'frame'        => ['label' => 'Frame / mainframe',              'kapasitas_kn' => 36.0, 'hb_maks' => 4.0, 'bay_m' => 1.8],
        'mobile'       => ['label' => 'Mobile tower beroda',            'kapasitas_kn' => 28.0, 'hb_maks' => 3.0, 'bay_m' => 1.8],
        'kantilever'   => ['label' => 'Kantilever / gantung',           'kapasitas_kn' => 44.0, 'hb_maks' => 2.5, 'bay_m' => 1.8],
    ];

    /** Kelas beban kerja aman (SWL) per m² dek. */
    public const KELAS = [
        'ringan' => ['label' => 'Ringan — inspeksi, pengecatan',          'swl' => 120],
        'sedang' => ['label' => 'Sedang — perpipaan, mekanikal, belt',    'swl' => 240],
        'berat'  => ['label' => 'Berat — refraktori, liner chute, pasangan bata', 'swl' => 360],
    ];

    /** Daya dukung izin permukaan tumpuan (kN/m²). */
    public const TANAH = [
        'beton'   => ['label' => 'Lantai beton bertulang',         'izin' => 250.0],
        'aspal'   => ['label' => 'Aspal / paving',                 'izin' => 150.0],
        'padat'   => ['label' => 'Tanah padat / sirtu dipadatkan', 'izin' => 100.0],
        'lunak'   => ['label' => 'Tanah lunak / timbunan',         'izin' => 50.0],
    ];

    /** Area kerja yang lazim di tambang dan fasilitas pengolahannya. */
    public const ZONA = [
        'CHPP & Crusher',
        'Konveyor Overland',
        'Workshop & Fuel Bay',
        'Pelabuhan & Jetty',
        'Power House',
        'Fuel Farm & Tangki',
        'Smelter & Pengolahan',
        'Kantor, Mess & Fasilitas',
    ];

    public const JENIS_INSPEKSI = [
        'awal'             => 'Awal — sebelum pertama dipakai',
        'mingguan'         => 'Mingguan (7 hari)',
        'pasca_modifikasi' => 'Sesudah modifikasi',
        'pasca_cuaca'      => 'Sesudah hujan lebat / angin kencang',
    ];

    /**
     * Daftar periksa 16 butir, empat kelompok. Gagal satu butir KRITIS
     * langsung menahan unit dengan tag merah.
     */
    public const PERIKSA = [
        ['kode' => 'INS-01', 'grup' => 'Pondasi & tumpuan',    'kritis' => true,  'label' => 'Permukaan rata, padat, bebas genangan dan galian tidak stabil'],
        ['kode' => 'INS-02', 'grup' => 'Pondasi & tumpuan',    'kritis' => true,  'label' => 'Base plate dan sole board di seluruh tiang, tanpa bata atau balok lepas'],
        ['kode' => 'INS-03', 'grup' => 'Pondasi & tumpuan',    'kritis' => true,  'label' => 'Screw jack tidak melebihi ulir aman (≤ 300 mm)'],
        ['kode' => 'INS-04', 'grup' => 'Pondasi & tumpuan',    'kritis' => true,  'label' => 'Roda terkunci dan outrigger terpasang (mobile tower)'],
        ['kode' => 'INS-05', 'grup' => 'Struktur & ikatan',    'kritis' => true,  'label' => 'Tiang tegak (plumb), tidak bengkok, tidak berkarat berat'],
        ['kode' => 'INS-06', 'grup' => 'Struktur & ikatan',    'kritis' => true,  'label' => 'Ledger dan transom lengkap dan kencang di setiap lift'],
        ['kode' => 'INS-07', 'grup' => 'Struktur & ikatan',    'kritis' => true,  'label' => 'Bracing diagonal terpasang sesuai rancangan'],
        ['kode' => 'INS-08', 'grup' => 'Struktur & ikatan',    'kritis' => true,  'label' => 'Ikatan ke struktur permanen kencang sesuai kebutuhan rasio H/B'],
        ['kode' => 'INS-09', 'grup' => 'Dek & akses',          'kritis' => true,  'label' => 'Plank penuh, rapat (celah ≤ 25 mm), terkunci tidak bergeser'],
        ['kode' => 'INS-10', 'grup' => 'Dek & akses',          'kritis' => true,  'label' => 'Plank tidak retak, tidak licin oli, overhang ≤ 300 mm'],
        ['kode' => 'INS-11', 'grup' => 'Dek & akses',          'kritis' => true,  'label' => 'Tangga terikat dan menjulang ≥ 1 m di atas dek'],
        ['kode' => 'INS-12', 'grup' => 'Dek & akses',          'kritis' => false, 'label' => 'Dek bersih, tidak ada tumpukan material melebihi SWL'],
        ['kode' => 'INS-13', 'grup' => 'Proteksi jatuh & area', 'kritis' => true,  'label' => 'Top rail (0,95–1,15 m) dan mid rail di seluruh sisi terbuka'],
        ['kode' => 'INS-14', 'grup' => 'Proteksi jatuh & area', 'kritis' => true,  'label' => 'Toe board ≥ 150 mm mengelilingi dek kerja'],
        ['kode' => 'INS-15', 'grup' => 'Proteksi jatuh & area', 'kritis' => true,  'label' => 'Jarak aman ≥ 3 m dari jaringan listrik, pipa panas, belt berjalan'],
        ['kode' => 'INS-16', 'grup' => 'Proteksi jatuh & area', 'kritis' => false, 'label' => 'Barikade drop zone dan rambu terpasang di bawah'],
    ];

    public const WARNA = ['hijau', 'kuning', 'merah'];
    public const STATUS = ['hijau', 'kuning', 'merah', 'dibongkar'];

    public static function swl(string $kelas): int
    {
        return (int) (self::KELAS[$kelas]['swl'] ?? 240);
    }

    /** Daftar pilih siap kirim ke tampilan. */
    public static function pilihan(): array
    {
        $peta = fn (array $a, string $kunciLabel = 'label') => collect($a)
            ->map(fn ($v, $k) => ['nilai' => $k, 'label' => is_array($v) ? $v[$kunciLabel] : $v])->values()->all();

        return [
            'jenis'     => $peta(self::JENIS),
            'kelas'     => collect(self::KELAS)->map(fn ($v, $k) => ['nilai' => $k, 'label' => $v['label'], 'swl' => $v['swl']])->values()->all(),
            'tanah'     => collect(self::TANAH)->map(fn ($v, $k) => ['nilai' => $k, 'label' => $v['label'], 'izin' => $v['izin']])->values()->all(),
            'zona'      => self::ZONA,
            'inspeksi'  => $peta(self::JENIS_INSPEKSI),
            'periksa'   => self::PERIKSA,
            'masa'      => self::MASA_BERLAKU_HARI,
            'sf_min'    => self::SF_MIN,
        ];
    }
}
