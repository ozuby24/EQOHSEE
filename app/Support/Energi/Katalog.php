<?php

namespace App\Support\Energi;

/**
 * Katalog sumber energi & emisi yang dikenal mesin perhitungan.
 *
 * Kode di sini adalah `kode_energi` pada registri faktor. Yang ditentukan
 * katalog hanyalah BENTUK hitungannya — satuan masukan, scope, kategori
 * IPCC bawaan — bukan angkanya; seluruh angka ada di registri dan dapat
 * diganti tanpa menyentuh kode.
 *
 * Jenis hitungan:
 *   cair     volume × densitas × NCV → GJ; CO₂/CH₄/N₂O per TJ (BBM, gas m³)
 *   massa    massa × NCV → GJ; CO₂/CH₄/N₂O per TJ (LPG)
 *   listrik  kWh × 3,6 MJ; emisi Scope 2 dari faktor grid sistemnya
 *   ebt      kWh × 3,6 MJ; tanpa emisi (PLTS di lokasi)
 *   peledak  massa × faktor stoikiometri tCO₂/t (proses, Scope 1)
 *   fugitive massa isi ulang × GWP (refrigeran, SF₆)
 */
final class Katalog
{
    public const ENERGI = [
        'solar'          => ['label' => 'Solar / biosolar',             'satuan' => 'L',   'jenis' => 'cair',     'scope' => 1, 'kategori' => 'bergerak_offroad', 'gas' => 'CO2'],
        'bensin'         => ['label' => 'Bensin',                       'satuan' => 'L',   'jenis' => 'cair',     'scope' => 1, 'kategori' => 'bergerak_jalan',   'gas' => 'CO2'],
        'lpg'            => ['label' => 'LPG',                          'satuan' => 'kg',  'jenis' => 'massa',    'scope' => 1, 'kategori' => 'stasioner',        'gas' => 'CO2'],
        'gas'            => ['label' => 'Gas alam',                     'satuan' => 'm³',  'jenis' => 'cair',     'scope' => 1, 'kategori' => 'stasioner',        'gas' => 'CO2'],
        'listrik_pln'    => ['label' => 'Listrik PLN',                  'satuan' => 'kWh', 'jenis' => 'listrik',  'scope' => 2, 'kategori' => 'listrik_beli',     'gas' => 'CO2e'],
        'listrik_iuptls' => ['label' => 'Listrik IUPTLS / pihak lain',  'satuan' => 'kWh', 'jenis' => 'listrik',  'scope' => 2, 'kategori' => 'listrik_beli',     'gas' => 'CO2e'],
        'plts'           => ['label' => 'PLTS / EBT di lokasi',         'satuan' => 'kWh', 'jenis' => 'ebt',      'scope' => 0, 'kategori' => null,               'gas' => null],
        'anfo'           => ['label' => 'Bahan peledak ANFO',           'satuan' => 'kg',  'jenis' => 'peledak',  'scope' => 1, 'kategori' => 'proses',           'gas' => 'CO2'],
        'emulsi'         => ['label' => 'Bahan peledak emulsi',         'satuan' => 'kg',  'jenis' => 'peledak',  'scope' => 1, 'kategori' => 'proses',           'gas' => 'CO2'],
        'r134a'          => ['label' => 'Refrigeran HFC-134a',          'satuan' => 'kg',  'jenis' => 'fugitive', 'scope' => 1, 'kategori' => 'fugitive',         'gas' => 'HFC'],
        'r410a'          => ['label' => 'Refrigeran R-410A',            'satuan' => 'kg',  'jenis' => 'fugitive', 'scope' => 1, 'kategori' => 'fugitive',         'gas' => 'HFC'],
        'r32'            => ['label' => 'Refrigeran HFC-32',            'satuan' => 'kg',  'jenis' => 'fugitive', 'scope' => 1, 'kategori' => 'fugitive',         'gas' => 'HFC'],
        'sf6'            => ['label' => 'SF₆ switchgear',               'satuan' => 'kg',  'jenis' => 'fugitive', 'scope' => 1, 'kategori' => 'fugitive',         'gas' => 'SF6'],
    ];

    /** Kode yang dapat dicatat lewat formulir "Gas & energi lain" (energy_other_logs.jenis). */
    public const LAIN = ['gas', 'bensin', 'lpg', 'anfo', 'emulsi', 'r134a', 'r410a', 'r32', 'sf6'];

    /** Kategori IPCC — penentu EF CH₄/N₂O dan kategori Scope 1. */
    public const KATEGORI_IPCC = [
        'stasioner'        => 'Pembakaran stasioner',
        'bergerak_offroad' => 'Pembakaran bergerak — off-road',
        'bergerak_jalan'   => 'Pembakaran bergerak — jalan',
        'proses'           => 'Emisi proses',
        'fugitive'         => 'Emisi fugitive',
        'listrik_beli'     => 'Listrik yang dibeli',
    ];

    /** Parameter registri beserta satuan bakunya. */
    public const PARAMETER = [
        'densitas'    => 'kg/L (gas: kg/m³)',
        'ncv'         => 'MJ/kg',
        'ef_co2'      => 'kgCO₂/TJ (peledak: tCO₂/t)',
        'ef_ch4'      => 'kgCH₄/TJ',
        'ef_n2o'      => 'kgN₂O/TJ',
        'ef_grid'     => 'tCO₂e/MWh',
        'ef_kontrak'  => 'tCO₂e/MWh (market-based)',
        'ef_residual' => 'tCO₂e/MWh (market-based)',
        'harga'       => 'Rp/satuan',
        'persen_fame' => '% volume',
        'gwp_ar5'     => 'tCO₂e/t',
        'gwp_ar6'     => 'tCO₂e/t',
        'gj_per_toe'  => 'GJ/TOE',
        'gj_per_sbm'  => 'GJ/SBM',
        'harga_dasar' => 'Rp/tCO₂e',
        'harga_rendah'=> 'Rp/tCO₂e',
        'harga_tinggi'=> 'Rp/tCO₂e',
    ];

    /** Nama parameter untuk teks yang dibaca orang (satuannya di PARAMETER). */
    public const NAMA_PARAMETER = [
        'densitas' => 'densitas', 'ncv' => 'nilai kalor', 'ef_co2' => 'faktor emisi CO₂', 'ef_ch4' => 'faktor emisi CH₄',
        'ef_n2o' => 'faktor emisi N₂O', 'ef_grid' => 'faktor emisi grid', 'ef_kontrak' => 'faktor emisi kontrak',
        'ef_residual' => 'faktor emisi residual', 'harga' => 'harga', 'persen_fame' => 'campuran FAME',
        'gwp_ar5' => 'GWP AR5', 'gwp_ar6' => 'GWP AR6', 'gj_per_toe' => 'GJ per TOE', 'gj_per_sbm' => 'GJ per SBM',
        'harga_dasar' => 'harga karbon dasar', 'harga_rendah' => 'harga karbon rendah', 'harga_tinggi' => 'harga karbon tinggi',
    ];

    /** Kode registri di luar sumber energi: gas rumah kaca, konversi, harga karbon. */
    public const KODE_LAIN = [
        'biodiesel_fame' => 'Biodiesel (FAME)', 'ch4' => 'Metana (CH₄)', 'n2o' => 'Dinitrogen oksida (N₂O)',
        'konversi' => 'Konversi satuan energi', 'karbon' => 'Harga karbon',
    ];

    /** Ketidakpastian data aktivitas menurut sumber datanya (FSD §6.9), dalam persen. */
    public const KETIDAKPASTIAN_AKTIVITAS = ['flowmeter' => 2.0, 'nota' => 5.0, 'fms' => 5.0, 'estimasi' => 20.0];

    /** Sumber listrik pada energy_power_logs → kode registri. Genset: solarnya yang dihitung. */
    public const SUMBER_LISTRIK = ['pln' => 'listrik_pln', 'iuptls' => 'listrik_iuptls', 'plts' => 'plts', 'genset' => null];

    public static function label(string $kode): string
    {
        return self::ENERGI[$kode]['label'] ?? self::KODE_LAIN[$kode] ?? ($kode === 'scope3' ? 'Rantai nilai' : $kode);
    }

    public static function satuan(string $kode): string
    {
        return self::ENERGI[$kode]['satuan'] ?? '';
    }

    /** Semua kode yang dapat diberi faktor, berlabel, untuk pilihan formulir. */
    public static function semuaKode(): array
    {
        $out = [];
        foreach (self::ENERGI as $k => $v) $out[$k] = $v['label'];

        return $out + self::KODE_LAIN;
    }
}
