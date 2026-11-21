<?php

namespace App\Support\Energi;

use App\Models\Energi\Faktor;

/**
 * Paket faktor acuan awal (FSD §17) — faktor BERSAMA, company_id NULL.
 *
 * Isinya nilai bawaan IPCC 2006 Vol. 2 dan acuan umum. Yang belum dapat
 * dicocokkan dengan dokumen sumbernya ditandai `perlu_cocok`: angkanya
 * tetap dipakai supaya modul dapat bekerja sejak hari pertama, tetapi
 * setiap layar dan laporan yang memakainya menyebutnya sebagai peringatan,
 * dan Petugas GRK yang mencocokkannya mematikan tandanya.
 *
 * Faktor grid listrik SENGAJA hanya satu nilai nasional warisan modul lama:
 * nilai per sistem ketenagalistrikan wajib disalin dari lampiran Kepmen
 * ESDM 163.K/HK.02/MEM.S/2021 atau penetapan terbaru Ditjen Ketenagalistrikan
 * [A-03], bukan ditebak di sini.
 *
 * Dipasang `php artisan energi:pasang` pada setiap deploy. Tidak menimpa
 * apa pun: baris yang sudah ada — termasuk yang sudah disetujui atau
 * ditarik orang — dibiarkan.
 */
final class PaketFaktor
{
    private const IPCC = 'IPCC 2006 Guidelines Vol. 2 (Energi)';
    private const MULAI = '2000-01-01';

    /**
     * [kode, parameter, kategori_ipcc, nilai, satuan, mulai, sampai, sumber, rujukan, tier, ketidakpastian%, perlu_cocok, catatan]
     *
     * @return list<array>
     */
    public static function daftar(): array
    {
        $m = self::MULAI;
        $i = self::IPCC;

        return [
            /* ── solar (gas/diesel oil) ── */
            ['solar', 'ncv', null, 43.0, 'MJ/kg', $m, null, $i, 'Tabel 1.2', 1, 2.0, false, null],
            ['solar', 'densitas', null, 0.84, 'kg/L', $m, null, 'Asumsi umum densitas solar', null, 1, 2.0, true,
                'Ganti dengan densitas dari sertifikat mutu pemasok.'],
            ['solar', 'ef_co2', null, 74100, 'kgCO2/TJ', $m, null, $i, 'Tabel 1.4', 1, 1.0, false, null],
            ['solar', 'ef_ch4', 'stasioner', 3, 'kgCH4/TJ', $m, null, $i, 'Tabel 2.2–2.3', 1, 100.0, false, null],
            ['solar', 'ef_n2o', 'stasioner', 0.6, 'kgN2O/TJ', $m, null, $i, 'Tabel 2.2–2.3', 1, 150.0, false, null],
            ['solar', 'ef_ch4', 'bergerak_offroad', 4.15, 'kgCH4/TJ', $m, null, $i, 'Tabel 3.3.1 (industri)', 1, 100.0, false, null],
            ['solar', 'ef_n2o', 'bergerak_offroad', 28.6, 'kgN2O/TJ', $m, null, $i, 'Tabel 3.3.1 (industri)', 1, 150.0, false, null],
            ['solar', 'ef_ch4', 'bergerak_jalan', 3.9, 'kgCH4/TJ', $m, null, $i, 'Tabel 3.2.2', 1, 100.0, false, null],
            ['solar', 'ef_n2o', 'bergerak_jalan', 3.9, 'kgN2O/TJ', $m, null, $i, 'Tabel 3.2.2', 1, 150.0, false, null],
            ['solar', 'harga', null, 14500, 'Rp/L', $m, null, 'Nilai warisan modul Energi (Rp14.500/L)', null, 1, null, true,
                'Ganti dengan harga kontrak/realisasi pembelian perusahaan.'],

            /* ── campuran biodiesel nasional (persen volume FAME) ── */
            ['solar', 'persen_fame', null, 30, '%', '2020-01-01', '2023-01-31', 'Mandatori B30 (Kepmen ESDM pemanfaatan BBN)', null, 1, null, true, null],
            ['solar', 'persen_fame', null, 35, '%', '2023-02-01', '2024-12-31', 'Mandatori B35 (Kepmen ESDM pemanfaatan BBN)', null, 1, null, true, null],
            ['solar', 'persen_fame', null, 40, '%', '2025-01-01', '2026-06-30', 'Mandatori B40 (Kepmen ESDM pemanfaatan BBN 2025) [R-18]', null, 1, null, true,
                'Nomor Kepmen belum diverifikasi.'],
            ['solar', 'persen_fame', null, 50, '%', '2026-07-01', null, 'Kepmen ESDM No. 257.K/EK.01/MEM.E/2026 (B50) [R-17]', null, 1, null, false,
                'Stok B40 boleh disalurkan s.d. 30 Sep 2026 — isi persen FAME per pengiriman bila menerima B40.'],

            /* ── biodiesel FAME ── */
            ['biodiesel_fame', 'ncv', null, 27.0, 'MJ/kg', $m, null, $i, 'Tabel 1.2 (Biodiesels)', 1, 5.0, true,
                'Nilai bawaan IPCC; NCV FAME hasil uji umumnya ±37 MJ/kg — ganti dengan sertifikat pemasok.'],
            ['biodiesel_fame', 'densitas', null, 0.88, 'kg/L', $m, null, 'Asumsi umum densitas FAME', null, 1, 2.0, true, null],
            ['biodiesel_fame', 'ef_co2', null, 70800, 'kgCO2/TJ', $m, null, $i, 'Tabel 1.4 (biogenik)', 1, 5.0, false,
                'CO₂ biogenik — dilaporkan terpisah, tidak masuk total Scope 1.'],

            /* ── bensin ── */
            ['bensin', 'ncv', null, 44.3, 'MJ/kg', $m, null, $i, 'Tabel 1.2', 1, 2.0, false, null],
            ['bensin', 'densitas', null, 0.74, 'kg/L', $m, null, 'Asumsi umum densitas bensin', null, 1, 2.0, true, null],
            ['bensin', 'ef_co2', null, 69300, 'kgCO2/TJ', $m, null, $i, 'Tabel 1.4', 1, 1.0, false, null],
            ['bensin', 'ef_ch4', 'bergerak_jalan', 33, 'kgCH4/TJ', $m, null, $i, 'Tabel 3.2.2 (tanpa katalis)', 1, 100.0, false, null],
            ['bensin', 'ef_n2o', 'bergerak_jalan', 3.2, 'kgN2O/TJ', $m, null, $i, 'Tabel 3.2.2 (tanpa katalis)', 1, 150.0, false, null],
            ['bensin', 'ef_ch4', 'stasioner', 3, 'kgCH4/TJ', $m, null, $i, 'Tabel 2.2', 1, 100.0, false, null],
            ['bensin', 'ef_n2o', 'stasioner', 0.6, 'kgN2O/TJ', $m, null, $i, 'Tabel 2.2', 1, 150.0, false, null],

            /* ── LPG ── */
            ['lpg', 'ncv', null, 47.3, 'MJ/kg', $m, null, $i, 'Tabel 1.2', 1, 2.0, false, null],
            ['lpg', 'ef_co2', null, 63100, 'kgCO2/TJ', $m, null, $i, 'Tabel 1.4', 1, 1.0, false, null],
            ['lpg', 'ef_ch4', 'stasioner', 1, 'kgCH4/TJ', $m, null, $i, 'Tabel 2.3', 1, 100.0, false, null],
            ['lpg', 'ef_n2o', 'stasioner', 0.1, 'kgN2O/TJ', $m, null, $i, 'Tabel 2.3', 1, 150.0, false, null],

            /* ── gas alam ── */
            ['gas', 'ncv', null, 48.0, 'MJ/kg', $m, null, $i, 'Tabel 1.2', 1, 2.0, false, null],
            ['gas', 'densitas', null, 0.777, 'kg/m3', $m, null, 'Setara konstanta lama 37,3 MJ/m³', null, 1, 5.0, true,
                'Ganti dengan densitas/GHV dari pemasok gas.'],
            ['gas', 'ef_co2', null, 56100, 'kgCO2/TJ', $m, null, $i, 'Tabel 1.4', 1, 1.0, false, null],
            ['gas', 'ef_ch4', 'stasioner', 1, 'kgCH4/TJ', $m, null, $i, 'Tabel 2.3', 1, 100.0, false, null],
            ['gas', 'ef_n2o', 'stasioner', 0.1, 'kgN2O/TJ', $m, null, $i, 'Tabel 2.3', 1, 150.0, false, null],
            ['gas', 'harga', null, 6500, 'Rp/m3', $m, null, 'Nilai warisan modul Energi (Rp6.500/m³)', null, 1, null, true, null],

            /* ── listrik ── */
            ['listrik_pln', 'ef_grid', null, 0.87, 'tCO2e/MWh', $m, null, 'Nilai warisan modul Energi (±0,87 kgCO₂e/kWh)', null, 1, 10.0, true,
                'GANTI dengan faktor per sistem dari lampiran Kepmen ESDM 163.K/HK.02/MEM.S/2021 [R-10][A-03].'],
            ['listrik_iuptls', 'ef_grid', null, 0.87, 'tCO2e/MWh', $m, null, 'Nilai warisan modul Energi (±0,87 kgCO₂e/kWh)', null, 1, 10.0, true,
                'GANTI dengan faktor pembangkit pemasok (IUPTLS) atau faktor grid sistemnya.'],
            ['listrik_pln', 'harga', null, 1450, 'Rp/kWh', $m, null, 'Nilai warisan modul Energi (Rp1.450/kWh)', null, 1, null, true, null],
            ['listrik_iuptls', 'harga', null, 1450, 'Rp/kWh', $m, null, 'Nilai warisan modul Energi (Rp1.450/kWh)', null, 1, null, true, null],

            /* ── bahan peledak [A-09] ── */
            ['anfo', 'ef_co2', null, 0.18, 'tCO2/t', $m, null, 'Stoikiometri ANFO (5,5–6% minyak solar)', null, 1, 10.0, true,
                'Rentang 0,17–0,19 tCO₂/t; sesuaikan dengan formulasi pemasok.'],
            ['emulsi', 'ef_co2', null, 0.17, 'tCO2/t', $m, null, 'Stoikiometri emulsi (kandungan minyak ±6%)', null, 1, 15.0, true,
                'Sesuaikan dengan formulasi pemasok.'],

            /* ── GWP 100 tahun ── */
            ['ch4', 'gwp_ar5', null, 28, 'tCO2e/t', $m, null, 'IPCC AR5 WG1', 'Tabel 8.A.1', 1, null, false, null],
            ['n2o', 'gwp_ar5', null, 265, 'tCO2e/t', $m, null, 'IPCC AR5 WG1', 'Tabel 8.A.1', 1, null, false, null],
            ['sf6', 'gwp_ar5', null, 23500, 'tCO2e/t', $m, null, 'IPCC AR5 WG1', 'Tabel 8.A.1', 1, null, false, null],
            ['r134a', 'gwp_ar5', null, 1300, 'tCO2e/t', $m, null, 'IPCC AR5 WG1', 'Tabel 8.A.1', 1, null, false, null],
            ['r32', 'gwp_ar5', null, 677, 'tCO2e/t', $m, null, 'IPCC AR5 WG1', 'Tabel 8.A.1', 1, null, false, null],
            ['r410a', 'gwp_ar5', null, 1924, 'tCO2e/t', $m, null, 'IPCC AR5 WG1 (campuran 50% HFC-32 + 50% HFC-125)', null, 1, null, true, null],
            ['ch4', 'gwp_ar6', null, 29.8, 'tCO2e/t', $m, null, 'IPCC AR6 WG1 (CH₄ fosil)', 'Tabel 7.15', 1, null, false, null],
            ['n2o', 'gwp_ar6', null, 273, 'tCO2e/t', $m, null, 'IPCC AR6 WG1', 'Tabel 7.15', 1, null, false, null],
            ['sf6', 'gwp_ar6', null, 25200, 'tCO2e/t', $m, null, 'IPCC AR6 WG1', 'Tabel 7.15', 1, null, false, null],
            ['r134a', 'gwp_ar6', null, 1530, 'tCO2e/t', $m, null, 'IPCC AR6 WG1', 'Tabel 7.15', 1, null, false, null],
            ['r32', 'gwp_ar6', null, 771, 'tCO2e/t', $m, null, 'IPCC AR6 WG1', 'Tabel 7.15', 1, null, false, null],
            ['r410a', 'gwp_ar6', null, 2255.5, 'tCO2e/t', $m, null, 'IPCC AR6 WG1 (campuran 50% HFC-32 + 50% HFC-125)', null, 1, null, true, null],

            /* ── konversi & harga karbon ── */
            ['konversi', 'gj_per_toe', null, 41.868, 'GJ/TOE', $m, null, 'Definisi IEA (1 TOE = 41,868 GJ) [A-02]', null, 1, null, false, null],
            ['konversi', 'gj_per_sbm', null, 6.1178632, 'GJ/SBM', $m, null, 'Setara barel minyak (BOE)', null, 1, null, true,
                'Cocokkan dengan faktor Handbook of Energy & Economic Statistics of Indonesia (ESDM).'],
            ['karbon', 'harga_dasar', null, 30000, 'Rp/tCO2e', $m, null, 'UU 7/2021 HPP — tarif minimum pajak karbon Rp30/kgCO₂e [R-12]', null, 1, null, false,
                'Harga karbon bayangan acuan minimum; penerapan pajak karbon masih ditunda.'],
        ];
    }

    /** @return array{baru:int,ada:int} */
    public static function pasang(): array
    {
        $baru = $ada = 0;

        foreach (self::daftar() as [$kode, $param, $kat, $nilai, $satuan, $mulai, $sampai, $sumber, $rujukan, $tier, $u, $cocok, $catatan]) {
            $kunci = Faktor::withoutGlobalScopes()->whereNull('company_id')
                ->where('kode_energi', $kode)->where('parameter', $param)
                ->where(fn ($q) => $kat === null ? $q->whereNull('kategori_ipcc') : $q->where('kategori_ipcc', $kat))
                ->whereNull('wilayah')->whereNull('pemasok')
                ->whereDate('berlaku_mulai', $mulai);

            if ($kunci->exists()) { $ada++; continue; }

            Faktor::withoutGlobalScopes()->create([
                'company_id' => null, 'kode_energi' => $kode, 'parameter' => $param, 'kategori_ipcc' => $kat,
                'nilai' => $nilai, 'satuan' => $satuan, 'berlaku_mulai' => $mulai, 'berlaku_sampai' => $sampai,
                'tier' => $tier, 'sumber_dokumen' => $sumber, 'sumber_rujukan' => $rujukan,
                'ketidakpastian_persen' => $u, 'status' => 'aktif', 'perlu_cocok' => $cocok, 'catatan' => $catatan,
            ]);
            $baru++;
        }

        RegistriFaktor::lupakan();

        return ['baru' => $baru, 'ada' => $ada];
    }
}
