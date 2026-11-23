<?php

namespace App\Support\Lca;

/**
 * Acuan modul Life Cycle Assessment.
 *
 * Lima tahap daur hidup disebut menurut kegiatan tambang, enam kategori
 * dampak dengan acuan normalisasinya (per kapita per tahun, dari modul
 * EcoTrace), konversi satuan, harga karbon untuk simulasi, dan pilihan
 * goal & scope ISO 14044.
 */
final class Katalog
{
    public const TAHAP = [
        'ekstraksi'  => ['label' => 'Ekstraksi & penambangan',       'pendek' => 'Ekstraksi',  'warna' => '#0F766E'],
        'angkut'     => ['label' => 'Angkutan & logistik',           'pendek' => 'Angkutan',   'warna' => '#0284C7'],
        'olah'       => ['label' => 'Pengolahan & energi',           'pendek' => 'Pengolahan', 'warna' => '#D97706'],
        'distribusi' => ['label' => 'Pemuatan & distribusi',         'pendek' => 'Distribusi', 'warna' => '#EA580C'],
        'akhir'      => ['label' => 'Pemakaian, limbah & reklamasi', 'pendek' => 'Akhir hidup', 'warna' => '#64748B'],
    ];

    /** Kategori dampak: kolom, satuan, metode, acuan normalisasi (per kapita/tahun). */
    public const DAMPAK = [
        'gwp' => ['kode' => 'GWP100', 'nama' => 'Pemanasan global',            'satuan' => 'kg CO₂e',     'metode' => 'IPCC 2021 AR6', 'normal' => 8100.0],
        'ap'  => ['kode' => 'AP',     'nama' => 'Asidifikasi',                 'satuan' => 'kg SO₂e',     'metode' => 'ReCiPe 2016 (H)', 'normal' => 41.0],
        'ep'  => ['kode' => 'EP',     'nama' => 'Eutrofikasi',                 'satuan' => 'kg PO₄e',     'metode' => 'CML-IA baseline', 'normal' => 1.6],
        'wsf' => ['kode' => 'WSF',    'nama' => 'Kelangkaan air',              'satuan' => 'm³ world eq', 'metode' => 'WULCA AWARE', 'normal' => 11469.0],
        'ced' => ['kode' => 'CED',    'nama' => 'Kebutuhan energi kumulatif', 'satuan' => 'MJ',          'metode' => 'Ecoinvent CED', 'normal' => 65000.0],
        'odp' => ['kode' => 'ODP',    'nama' => 'Penipisan ozon',             'satuan' => 'kg CFC-11e',  'metode' => 'ReCiPe 2016 (H)', 'normal' => 0.06],
    ];

    public const KATEGORI_FAKTOR = [
        'energi' => 'Energi & bahan bakar', 'material' => 'Material & reagen', 'peledak' => 'Bahan peledak',
        'transportasi' => 'Transportasi', 'kemasan' => 'Kemasan', 'air' => 'Air', 'limbah' => 'Limbah & daur ulang',
        'lahan' => 'Lahan & reklamasi', 'pembakaran' => 'Pemakaian produk',
    ];

    /** Konversi ke satuan dasar kelompoknya. */
    public const KONVERSI = [
        'massa'  => ['kg' => 1.0, 'ton' => 1000.0, 't' => 1000.0, 'g' => 0.001],
        'energi' => ['kWh' => 1.0, 'MWh' => 1000.0, 'MJ' => 0.2777778, 'GJ' => 277.77778],
        'volume' => ['L' => 1.0, 'kL' => 1000.0, 'm3' => 1000.0],
        'luas'   => ['ha' => 1.0, 'm2' => 0.0001],
        'angkut' => ['tkm' => 1.0],
    ];

    public const BATAS = [
        'cradle_to_gate'   => 'Cradle-to-gate (sampai gerbang/pelabuhan muat)',
        'cradle_to_grave'  => 'Cradle-to-grave (termasuk pemakaian & akhir hidup)',
        'gate_to_gate'     => 'Gate-to-gate (proses internal saja)',
        'cradle_to_cradle' => 'Cradle-to-cradle (dengan kredit daur ulang)',
    ];

    public const METODE = ['IPCC_2021_GWP100' => 'IPCC 2021 GWP100', 'RECIPE_2016_H' => 'ReCiPe 2016 Midpoint (H)', 'CML_IA' => 'CML-IA baseline'];
    public const ALOKASI = ['massa' => 'Massa', 'ekonomi' => 'Ekonomi', 'energi' => 'Kandungan energi', 'perluasan' => 'Perluasan sistem', 'tidak_ada' => 'Tanpa alokasi'];

    public const STATUS = [
        'draf'          => ['label' => 'Draf',          'kelas' => 'pr-abu'],
        'tinjau'        => ['label' => 'Dalam tinjauan', 'kelas' => 'pr-kuning'],
        'terverifikasi' => ['label' => 'Terverifikasi', 'kelas' => 'pr-hijau'],
        'terbit'        => ['label' => 'Terbit',        'kelas' => 'pr-biru'],
        'arsip'         => ['label' => 'Arsip',         'kelas' => 'pr-abu'],
    ];

    /** Studi yang angkanya dikunci. */
    public const TERKUNCI = ['terverifikasi', 'terbit', 'arsip'];

    /** Harga karbon untuk simulasi (asumsi, dapat diubah). */
    public const HARGA = [
        'idr_per_t' => 75000,     // skenario pasar karbon domestik (IDXCarbon)
        'idr_min'   => 30000,     // tarif minimum pajak karbon UU 7/2021 HPP
        'eur_per_t' => 80,        // acuan EU CBAM
        'kurs_eur'  => 17200,
    ];

    public const PEDIGREE = [
        'p_reliabilitas' => ['Reliabilitas', ['Terukur & terverifikasi', 'Terukur sebagian / dihitung', 'Estimasi berdasar data', 'Estimasi kualitatif', 'Tidak diketahui']],
        'p_kelengkapan'  => ['Kelengkapan',  ['Seluruh lokasi, periode cukup', '> 50% lokasi', '< 50% lokasi', 'Satu lokasi', 'Tidak diketahui']],
        'p_temporal'     => ['Korelasi waktu', ['< 3 tahun', '< 6 tahun', '< 10 tahun', '< 15 tahun', '> 15 tahun']],
        'p_geografis'    => ['Korelasi geografis', ['Lokasi yang sama', 'Wilayah serupa', 'Nasional', 'Regional', 'Global / tidak diketahui']],
        'p_teknologi'    => ['Korelasi teknologi', ['Proses yang sama', 'Proses serupa', 'Teknologi terkait', 'Teknologi berbeda', 'Tidak diketahui']],
    ];

    public static function satuanGrup(string $s): ?string
    {
        foreach (self::KONVERSI as $g => $daftar) if (isset($daftar[$s])) return $g;
        return null;
    }

    public static function konversi(float $q, string $dari, string $ke): float
    {
        if ($dari === $ke) return $q;
        foreach (self::KONVERSI as $daftar) {
            if (isset($daftar[$dari], $daftar[$ke])) return $q * $daftar[$dari] / $daftar[$ke];
        }
        return $q;
    }

    public static function mutu(?float $dqr): string
    {
        return match (true) {
            $dqr === null => '—',
            $dqr <= 1.6 => 'Sangat baik',
            $dqr <= 2.4 => 'Baik',
            $dqr <= 3.4 => 'Cukup',
            default => 'Lemah',
        };
    }

    public static function pilihan(): array
    {
        $daftar = fn (array $a) => collect($a)->map(fn ($v, $k) => ['nilai' => $k, 'label' => is_array($v) ? $v['label'] : $v])->values()->all();

        return [
            'tahap' => collect(self::TAHAP)->map(fn ($v, $k) => ['nilai' => $k] + $v)->values()->all(),
            'dampak' => collect(self::DAMPAK)->map(fn ($v, $k) => ['nilai' => $k] + $v)->values()->all(),
            'batas' => $daftar(self::BATAS), 'metode' => $daftar(self::METODE), 'alokasi' => $daftar(self::ALOKASI),
            'kategori' => $daftar(self::KATEGORI_FAKTOR),
            'pedigree' => collect(self::PEDIGREE)->map(fn ($v, $k) => ['kunci' => $k, 'label' => $v[0], 'tingkat' => $v[1]])->values()->all(),
            'satuan' => collect(self::KONVERSI)->map(fn ($v) => array_keys($v))->all(),
            'harga' => self::HARGA,
        ];
    }
}
