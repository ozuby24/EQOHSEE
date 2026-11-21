<?php

namespace App\Support\Energi;


/**
 * Mesin rumus energi & emisi (FSD §6) — satu-satunya tempat rumus berada.
 *
 * Masukannya selalu data aktivitas MENTAH (liter, kWh, m³, kg) beserta
 * TANGGAL aktivitasnya, karena faktor dipilih menurut tanggal itu: liter
 * solar Juni 2026 dibakar sebagai B40, Juli 2026 sebagai B50.
 *
 * Keluarannya bukan sekadar angka. Setiap hasil membawa daftar faktor yang
 * dipakai (untuk PanelRumus dan snapshot laporan) dan daftar faktor yang
 * TIDAK tersedia. Angka yang faktornya tidak tersedia bernilai null, bukan
 * nol: nol tCO₂e terbaca sebagai "tidak beremisi", padahal yang terjadi
 * adalah "tidak dapat dihitung" — dan hanya yang kedua yang jujur.
 *
 * Rumus pokok, untuk BBM dengan fraksi FAME f (volume):
 *
 *   e_fosil (MJ/L) = (1 − f) × ρ_solar × NCV_solar
 *   e_bio   (MJ/L) =      f  × ρ_FAME  × NCV_FAME
 *   GJ             = L × (e_fosil + e_bio) / 1000
 *   CO₂ fosil (t)  = GJ_fosil × EF_CO₂,solar (kg/TJ) / 10⁶
 *   CO₂ biogenik   = GJ_bio   × EF_CO₂,FAME  (kg/TJ) / 10⁶   → dilaporkan TERPISAH
 *   CH₄, N₂O (t)   = GJ × EF (kg/TJ, menurut kategori IPCC) / 10⁶
 *   CO₂e (t)       = CO₂ fosil + CH₄ × GWP_CH₄ + N₂O × GWP_N₂O
 *
 * Contoh uji (FSD §16.1): 1.000 L B0, ρ 0,84, NCV 43,0, EF 74.100 →
 * 36,12 GJ dan 2,676 tCO₂.
 */
final class Kalkulator
{
    public const MJ_PER_KWH = 3.6;

    /**
     * @param array{company_id?:?int,wilayah?:?string,pemasok?:?string,kategori_ipcc?:?string,
     *              persen_fame?:?float,gwp?:?string,sumber_data?:?string} $k
     * @return array<string,mixed>
     */
    public static function hitung(string $kode, float $jumlah, \DateTimeInterface|string $tanggal, array $k = []): array
    {
        $kat = Katalog::ENERGI[$kode] ?? null;

        $h = new HasilHitung($kode, $jumlah, $tanggal, $k + [
            'kategori_ipcc' => $k['kategori_ipcc'] ?? ($kat['kategori'] ?? null),
        ]);

        if (!$kat) {
            $h->kurang[] = $kode.' (kode energi tidak dikenal)';

            return $h->keluaran();
        }

        $h->scope = $kat['scope'];

        match ($kat['jenis']) {
            'cair', 'massa' => self::pembakaran($h, $kat['jenis']),
            'listrik'       => self::listrik($h),
            'ebt'           => self::ebt($h),
            'peledak'       => self::peledak($h),
            'fugitive'      => self::fugitive($h),
        };

        if ($kat['jenis'] !== 'ebt' && ($harga = $h->faktor($kode, 'harga', [], false)) !== null) {
            $h->rupiah = $jumlah * $harga;
        }

        return $h->keluaran();
    }

    /** GJ → TOE (1 TOE = 41,868 GJ, definisi IEA [A-02]); null bila faktornya tidak ada. */
    public static function toe(float $gj, \DateTimeInterface|string $tanggal): ?float
    {
        $f = RegistriFaktor::nilai('konversi', 'gj_per_toe', $tanggal);

        return $f ? $gj / $f : null;
    }

    public static function sbm(float $gj, \DateTimeInterface|string $tanggal): ?float
    {
        $f = RegistriFaktor::nilai('konversi', 'gj_per_sbm', $tanggal);

        return $f ? $gj / $f : null;
    }

    /** GWP satu gas pada set yang dipilih (AR5/AR6). */
    public static function gwp(string $gas, string $set, \DateTimeInterface|string $tanggal): ?float
    {
        return RegistriFaktor::nilai($gas, 'gwp_'.strtolower($set), $tanggal);
    }

    /* ─────────────── jenis hitungan ─────────────── */

    private static function pembakaran(HasilHitung $h, string $jenis): void
    {
        $kode = $h->kode;
        $kat  = ['kategori_ipcc' => $h->k['kategori_ipcc']];

        $ncv  = $h->faktor($kode, 'ncv');
        $efC  = $h->faktor($kode, 'ef_co2');
        $efM  = $h->faktor($kode, 'ef_ch4', $kat);
        $efN  = $h->faktor($kode, 'ef_n2o', $kat);
        $dens = $jenis === 'cair' ? $h->faktor($kode, 'densitas') : 1.0;

        /* Fraksi FAME hanya berlaku pada solar. Isian per baris (bukti
           pengiriman B40 di masa transisi B50) mengalahkan campuran
           nasional yang berlaku pada tanggal itu. */
        $fame = 0.0;
        if ($kode === 'solar') {
            $fame = $h->k['persen_fame'] ?? $h->faktor('solar', 'persen_fame', [], false);
            if ($fame === null) {
                $fame = 0.0;
                $h->catatan[] = 'Campuran FAME pada tanggal ini tidak tercatat di registri; dihitung sebagai B0.';
            }
        }
        $h->persenFame = (float) $fame;
        $f = max(0.0, min(100.0, (float) $fame)) / 100;

        if ($ncv === null || $dens === null) return;

        $eFosil = (1 - $f) * $dens * $ncv;
        $eBio   = 0.0;

        if ($f > 0) {
            $densF = $h->faktor('biodiesel_fame', 'densitas');
            $ncvF  = $h->faktor('biodiesel_fame', 'ncv');
            $efCF  = $h->faktor('biodiesel_fame', 'ef_co2');
            if ($densF === null || $ncvF === null) return;

            $eBio = $f * $densF * $ncvF;
            $h->co2Bio = $efCF !== null ? ($h->jumlah * $eBio / 1000) * $efCF / 1e6 : null;
        } else {
            $h->co2Bio = 0.0;
        }

        $gjFosil = $h->jumlah * $eFosil / 1000;
        $h->gj   = $gjFosil + $h->jumlah * $eBio / 1000;

        $h->co2Fosil = $efC !== null ? $gjFosil * $efC / 1e6 : null;
        $h->ch4      = $efM !== null ? $h->gj * $efM / 1e6 : null;
        $h->n2o      = $efN !== null ? $h->gj * $efN / 1e6 : null;

        $h->ketidakpastianFaktor = self::akar(
            $h->ketidakpastian($kode, 'ncv'), $h->ketidakpastian($kode, 'ef_co2'));
    }

    private static function listrik(HasilHitung $h): void
    {
        $h->gj = $h->jumlah * self::MJ_PER_KWH / 1000;
        $h->co2Fosil = 0.0; $h->ch4 = 0.0; $h->n2o = 0.0; $h->co2Bio = 0.0;

        $ef = $h->faktor($h->kode, 'ef_grid', ['wilayah' => $h->k['wilayah'] ?? null, 'kategori_ipcc' => null]);
        $h->lainCo2e = $ef !== null ? ($h->jumlah / 1000) * $ef : null;

        /* Market-based (GHG Protocol Scope 2 Guidance): faktor kontrak
           pemasok, lalu faktor residual; tanpa keduanya dipakai faktor grid
           dan ditandai, sebab angka itu sesungguhnya location-based. */
        $mb = $h->faktor($h->kode, 'ef_kontrak', ['pemasok' => $h->k['pemasok'] ?? null], false)
           ?? $h->faktor($h->kode, 'ef_residual', [], false);

        if ($mb === null && $ef !== null) {
            $mb = $ef;
            $h->catatan[] = 'Market-based memakai faktor grid: faktor kontrak/residual belum tersedia.';
        }

        $h->co2eMb = $mb !== null ? ($h->jumlah / 1000) * $mb : null;
        $h->ketidakpastianFaktor = $h->ketidakpastian($h->kode, 'ef_grid', ['wilayah' => $h->k['wilayah'] ?? null]);
    }

    private static function ebt(HasilHitung $h): void
    {
        $h->gj = $h->jumlah * self::MJ_PER_KWH / 1000;
        $h->co2Fosil = 0.0; $h->ch4 = 0.0; $h->n2o = 0.0; $h->lainCo2e = 0.0; $h->co2Bio = 0.0;
        $h->co2eMb = 0.0;
    }

    private static function peledak(HasilHitung $h): void
    {
        $ef = $h->faktor($h->kode, 'ef_co2');
        $h->co2Fosil = $ef !== null ? ($h->jumlah / 1000) * $ef : null;
        $h->ch4 = 0.0; $h->n2o = 0.0; $h->co2Bio = 0.0;
        $h->ketidakpastianFaktor = $h->ketidakpastian($h->kode, 'ef_co2');
    }

    private static function fugitive(HasilHitung $h): void
    {
        $g = $h->faktor($h->kode, 'gwp_'.strtolower($h->gwpSet));
        $h->lainCo2e = $g !== null ? ($h->jumlah / 1000) * $g : null;
        $h->co2Fosil = 0.0; $h->ch4 = 0.0; $h->n2o = 0.0; $h->co2Bio = 0.0;
    }

    private static function akar(?float ...$u): ?float
    {
        $ada = array_filter($u, fn ($x) => $x !== null);

        return $ada ? sqrt(array_sum(array_map(fn ($x) => $x * $x, $ada))) : null;
    }
}
