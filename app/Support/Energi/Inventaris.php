<?php

namespace App\Support\Energi;

use App\Models\{EnergyEquipment, EnergyFuelLog, EnergyOtherLog, EnergyPowerLog, EnergyProduction};
use App\Models\Energi\{Pengaturan, Scope3, Site};
use App\Support\{Db, Energi};
use Illuminate\Support\Carbon;

/**
 * Inventarisasi energi & emisi GRK (FSD §6, §8.4) dari catatan harian.
 *
 * Seluruh catatan dibaca LEWAT model, sehingga batas perusahaan
 * (MilikPerusahaan) tetap berlaku tanpa satu pun `where company_id` di
 * sini; saringan perusahaan/site hanya MEMPERSEMPIT di atas batas itu.
 *
 * Baris dikelompokkan per sumber per bulan, lalu dihitung dengan faktor
 * yang berlaku pada awal bulan aktivitasnya — campuran FAME dan faktor
 * grid memang berganti pada batas bulan. Ribuan baris harian menjadi
 * puluhan baris agregat, cukup ringan untuk dasbor direksi.
 *
 * Yang dikeluarkan adalah baris agregat apa adanya, termasuk yang tidak
 * dapat dihitung (`kurang` tidak kosong, angka emisinya null). Baris itu
 * tidak disembunyikan: periode yang memuatnya tidak dapat dikunci, dan
 * laporan yang memuatnya tidak dapat terbit.
 */
final class Inventaris
{
    /** Kode refrigeran (HFC) dan SF₆ — dipisah pada rincian per gas. */
    public const HFC = ['r134a', 'r410a', 'r32'];

    /** @var array<int|string,Pengaturan> */
    private array $atur = [];

    /** @var array<int,Site> */
    private array $site = [];

    /** @var array<int,array> faktor yang dipakai, menurut id */
    private array $faktor = [];

    private function __construct(private readonly ?string $gwp) {}

    /**
     * @param array{company_id?:?int,site_id?:?int,gwp?:?string} $saring
     * @return array{baris:list<array>,faktor:array<int,array>}
     */
    public static function hitung(Carbon|string $dari, Carbon|string $sampai, array $saring = []): array
    {
        $dari   = Carbon::parse($dari)->toDateString();
        $sampai = Carbon::parse($sampai)->toDateString();

        $i = new self($saring['gwp'] ?? null);
        $baris = array_merge(
            $i->bbmAlat($dari, $sampai, $saring),
            $i->listrik($dari, $sampai, $saring),
            $i->lain($dari, $sampai, $saring),
            $i->scope3($dari, $sampai, $saring),
        );

        return ['baris' => $baris, 'faktor' => $i->faktor];
    }

    /**
     * Rentang tanggal untuk whereBetween pada kolom DATE.
     *
     * Ujung atasnya ditulis sampai 23:59:59, bukan tanggal polos: SQLite
     * menyimpan kolom DATE sebagai untaian "Y-m-d 00:00:00", dan untaian
     * itu LEBIH BESAR daripada "Y-m-d" — hari terakhir rentang terbuang
     * diam-diam. PostgreSQL dan MySQL menafsirkan ujung yang sama sebagai
     * tanggal itu juga, jadi bentuk ini benar pada ketiganya.
     *
     * @return array{0:string,1:string}
     */
    public static function sela(Carbon|string $dari, Carbon|string $sampai): array
    {
        return [Carbon::parse($dari)->toDateString(), Carbon::parse($sampai)->toDateString().' 23:59:59'];
    }

    /** Produksi pembagi intensitas, per perusahaan dan total. */
    public static function produksi(Carbon|string $dari, Carbon|string $sampai, array $saring = []): array
    {
        $q = EnergyProduction::query()
            ->whereBetween('tanggal', self::sela($dari, $sampai))
            ->when($saring['company_id'] ?? null, fn ($q, $c) => $q->where('company_id', $c));

        $ton = (float) (clone $q)->sum('ton');
        $bcm = (float) (clone $q)->sum('bcm');

        return ['ton' => $ton, 'bcm' => $bcm];
    }

    /**
     * Jumlahan baris agregat menjadi angka inventarisasi.
     *
     * @param list<array> $baris
     */
    public static function ringkas(array $baris): array
    {
        $r = [
            'gj' => 0.0, 'toe' => 0.0, 'rupiah' => 0.0,
            's1' => 0.0, 's2_lb' => 0.0, 's2_mb' => 0.0, 's3' => 0.0, 's3_terpisah' => 0.0,
            'co2_bio' => 0.0, 'co2' => 0.0, 'ch4' => 0.0, 'n2o' => 0.0, 'hfc_co2e' => 0.0, 'sf6_co2e' => 0.0,
            'kwh_beli' => 0.0, 'kwh_ebt' => 0.0, 'kwh_genset' => 0.0, 'liter_solar' => 0.0, 'liter_genset' => 0.0,
            'kurang' => [], 'baris_kurang' => 0, 'tanpa_harga' => 0,
            'n' => 0, 'n_verif' => 0, 'n_est' => 0, 'terakhir' => null,
            'per_kode' => [], 'per_kategori_s1' => [],
        ];

        $uKuadrat = 0.0;
        $uBasis = 0.0;

        foreach ($baris as $b) {
            $r['n']       += $b['n'];
            $r['n_verif'] += $b['n_verif'];
            $r['n_est']   += $b['n_est'];
            if ($b['terakhir'] && ($r['terakhir'] === null || $b['terakhir'] > $r['terakhir'])) $r['terakhir'] = $b['terakhir'];

            if ($b['kurang']) {
                $r['baris_kurang']++;
                $r['kurang'] = array_values(array_unique(array_merge($r['kurang'], $b['kurang'])));
            }

            if ($b['scope'] === 3) {
                $r[$b['terpisah'] ? 's3_terpisah' : 's3'] += (float) $b['co2e_t'];
                continue;
            }

            $r['gj']  += (float) $b['gj'];
            $r['toe'] += (float) $b['toe'];

            if ($b['rupiah'] === null && $b['kode'] !== 'plts') $r['tanpa_harga']++;
            $r['rupiah'] += (float) $b['rupiah'];

            $k = $b['kode'];
            $r['per_kode'][$k] ??= ['label' => Katalog::label($k), 'jumlah' => 0.0, 'satuan' => $b['satuan'], 'gj' => 0.0, 'co2e_t' => 0.0];
            $r['per_kode'][$k]['jumlah'] += $b['jumlah'];
            $r['per_kode'][$k]['gj']     += (float) $b['gj'];
            $r['per_kode'][$k]['co2e_t'] += (float) $b['co2e_t'];

            if ($k === 'solar') $r['liter_solar'] += $b['jumlah'];
            if (($b['sumber']['jenis'] ?? null) === 'genset') $r['liter_genset'] += $b['jumlah'];
            if ($k === 'plts') $r['kwh_ebt'] += $b['jumlah'];
            if (in_array($k, ['listrik_pln', 'listrik_iuptls'], true)) $r['kwh_beli'] += $b['jumlah'];
            $r['kwh_genset'] += (float) ($b['kwh_genset'] ?? 0);

            if ($b['scope'] === 1) {
                $r['s1'] += (float) $b['co2e_t'];
                $kat = $b['kategori_ipcc'] ?? 'lain';
                $r['per_kategori_s1'][$kat] = ($r['per_kategori_s1'][$kat] ?? 0.0) + (float) $b['co2e_t'];

                $r['co2']     += (float) $b['co2_fosil_t'];
                $r['ch4']     += (float) $b['ch4_t'];
                $r['n2o']     += (float) $b['n2o_t'];
                $r['co2_bio'] += (float) $b['co2_bio_t'];
                if (in_array($k, self::HFC, true)) $r['hfc_co2e'] += (float) $b['lain_co2e_t'];
                if ($k === 'sf6') $r['sf6_co2e'] += (float) $b['lain_co2e_t'];
            } elseif ($b['scope'] === 2) {
                $r['s2_lb'] += (float) $b['co2e_t'];
                $r['s2_mb'] += (float) $b['co2e_mb_t'];
            }

            if ($b['ketidakpastian_persen'] !== null && $b['co2e_t']) {
                $uKuadrat += ($b['ketidakpastian_persen'] * $b['co2e_t']) ** 2;
                $uBasis   += $b['co2e_t'];
            }
        }

        $kwhTotal = $r['kwh_ebt'] + $r['kwh_beli'] + $r['kwh_genset'];

        return $r + [
            'total'               => $r['s1'] + $r['s2_lb'],
            'persen_ebt'          => $kwhTotal > 0 ? $r['kwh_ebt'] / $kwhTotal * 100 : null,
            'persen_terverifikasi'=> $r['n'] > 0 ? $r['n_verif'] / $r['n'] * 100 : null,
            'persen_estimasi'     => $r['n'] > 0 ? $r['n_est'] / $r['n'] * 100 : null,
            'ketidakpastian_persen' => $uBasis > 0 ? sqrt($uKuadrat) / $uBasis : null,
            'lengkap'             => $r['baris_kurang'] === 0,
        ];
    }

    /**
     * Kelompokkan baris menurut satu kunci, lalu ringkas tiap kelompok.
     *
     * @param list<array> $baris
     * @return array<string,array>
     */
    public static function kelompok(array $baris, callable $kunci): array
    {
        $g = [];
        foreach ($baris as $b) $g[(string) $kunci($b)][] = $b;
        ksort($g);

        return array_map(fn ($isi) => self::ringkas($isi), $g);
    }

    /* ═══════════════ sumber data ═══════════════ */

    private function bbmAlat(string $dari, string $sampai, array $s): array
    {
        $ym = Db::ym('tanggal');

        $agg = EnergyFuelLog::query()
            ->whereBetween('tanggal', self::sela($dari, $sampai))
            ->when($s['company_id'] ?? null, fn ($q, $c) => $q->whereHas('equipment', fn ($e) => $e->where('company_id', $c)))
            ->when($s['site_id'] ?? null, fn ($q, $site) => $q->whereHas('equipment', fn ($e) => $e->where('site_id', $site)))
            ->selectRaw("equipment_id, {$ym} as bulan, persen_fame, SUM(liter) as jumlah, COUNT(*) as n,
                SUM(CASE WHEN status IN ('terverifikasi','terkunci') THEN 1 ELSE 0 END) as n_verif,
                SUM(CASE WHEN sumber_data = 'estimasi' THEN 1 ELSE 0 END) as n_est, MAX(tanggal) as terakhir")
            ->groupByRaw("equipment_id, {$ym}, persen_fame")
            ->get();

        if ($agg->isEmpty()) return [];

        $alat = EnergyEquipment::withoutGlobalScopes()->whereIn('id', $agg->pluck('equipment_id')->unique())->get()->keyBy('id');

        $out = [];
        foreach ($agg as $a) {
            $u = $alat[$a->equipment_id] ?? null;
            if (!$u) continue;

            $out[] = $this->baris('solar', (float) $a->jumlah, $a->bulan, $u->company_id, $u->site_id, [
                'jenis' => 'alat', 'id' => $u->id, 'kode' => $u->kode, 'label' => $u->kode.' — '.$u->nama,
                'kategori' => $u->kategori, 'kelompok' => Energi::KATEGORI[$u->kategori] ?? $u->kategori,
            ], $a, ['kategori_ipcc' => $u->kategori_ipcc ?: 'bergerak_offroad',
                    'persen_fame' => $a->persen_fame !== null ? (float) $a->persen_fame : null]);
        }

        return $out;
    }

    private function listrik(string $dari, string $sampai, array $s): array
    {
        $ym = Db::ym('tanggal');

        $agg = EnergyPowerLog::query()
            ->whereBetween('tanggal', self::sela($dari, $sampai))
            ->when($s['company_id'] ?? null, fn ($q, $c) => $q->where('company_id', $c))
            ->when($s['site_id'] ?? null, fn ($q, $site) => $q->where('site_id', $site))
            ->selectRaw("company_id, site_id, area, sumber, {$ym} as bulan, SUM(kwh) as kwh, SUM(liter_genset) as liter, COUNT(*) as n,
                SUM(CASE WHEN status IN ('terverifikasi','terkunci') THEN 1 ELSE 0 END) as n_verif,
                SUM(CASE WHEN sumber_data = 'estimasi' THEN 1 ELSE 0 END) as n_est, MAX(tanggal) as terakhir")
            ->groupByRaw("company_id, site_id, area, sumber, {$ym}")
            ->get();

        $out = [];
        foreach ($agg as $a) {
            $area = Energi::AREA[$a->area] ?? $a->area;
            $sumber = ['jenis' => 'listrik', 'id' => null, 'kode' => $a->area.'/'.$a->sumber,
                       'label' => $area.' — '.(Energi::SUMBER_LISTRIK[$a->sumber] ?? $a->sumber),
                       'kategori' => $a->area, 'kelompok' => $area];

            if ($a->sumber === 'genset') {
                /* Genset: yang beremisi solarnya (Scope 1, stasioner). kWh-nya
                   hanya dicatat untuk % EBT dan EnPI genset — menjumlahkannya
                   lagi sebagai energi berarti menghitung energi yang sama dua kali. */
                if ((float) $a->liter <= 0) continue;
                $b = $this->baris('solar', (float) $a->liter, $a->bulan, $a->company_id, $a->site_id,
                    ['jenis' => 'genset'] + $sumber, $a, ['kategori_ipcc' => 'stasioner']);
                $b['kwh_genset'] = (float) $a->kwh;
                $out[] = $b;
                continue;
            }

            $kode = Katalog::SUMBER_LISTRIK[$a->sumber] ?? null;
            if (!$kode || (float) $a->kwh <= 0) continue;

            $out[] = $this->baris($kode, (float) $a->kwh, $a->bulan, $a->company_id, $a->site_id, $sumber, $a, [
                'wilayah' => $this->wilayah($a->company_id, $a->site_id),
            ]);
        }

        return $out;
    }

    private function lain(string $dari, string $sampai, array $s): array
    {
        $ym = Db::ym('tanggal');

        $agg = EnergyOtherLog::query()
            ->whereBetween('tanggal', self::sela($dari, $sampai))
            ->when($s['company_id'] ?? null, fn ($q, $c) => $q->where('company_id', $c))
            ->when($s['site_id'] ?? null, fn ($q, $site) => $q->where('site_id', $site))
            ->selectRaw("company_id, site_id, jenis, {$ym} as bulan, SUM(jumlah) as jumlah, COUNT(*) as n,
                SUM(CASE WHEN status IN ('terverifikasi','terkunci') THEN 1 ELSE 0 END) as n_verif,
                SUM(CASE WHEN sumber_data = 'estimasi' THEN 1 ELSE 0 END) as n_est, MAX(tanggal) as terakhir")
            ->groupByRaw("company_id, site_id, jenis, {$ym}")
            ->get();

        $out = [];
        foreach ($agg as $a) {
            if ((float) $a->jumlah <= 0) continue;

            $out[] = $this->baris($a->jenis, (float) $a->jumlah, $a->bulan, $a->company_id, $a->site_id, [
                'jenis' => 'lain', 'id' => null, 'kode' => $a->jenis, 'label' => Katalog::label($a->jenis),
                'kategori' => $a->jenis, 'kelompok' => Katalog::label($a->jenis),
            ], $a);
        }

        return $out;
    }

    private function scope3(string $dari, string $sampai, array $s): array
    {
        $d = Carbon::parse($dari);
        $z = Carbon::parse($sampai);

        $rows = Scope3::query()
            ->whereBetween('tahun', [$d->year, $z->year])
            ->when($s['company_id'] ?? null, fn ($q, $c) => $q->where('company_id', $c))
            ->get()
            ->filter(function (Scope3 $r) use ($d, $z) {
                if ($r->bulan === null) return true;
                $awal = Carbon::create($r->tahun, $r->bulan, 1);

                return $awal->gte($d->copy()->startOfMonth()) && $awal->lte($z);
            });

        $out = [];
        foreach ($rows as $r) {
            $t = $r->tco2e();
            $out[] = [
                'company_id' => $r->company_id, 'site_id' => null,
                /* Entri setahun ditempatkan di bulan terakhir rentang pada
                   tahun itu — bukan Desember yang mungkin belum datang. */
                'bulan' => sprintf('%04d-%02d', $r->tahun, $r->bulan ?? ($r->tahun === $z->year ? $z->month : 12)),
                'sumber' => ['jenis' => 'scope3', 'id' => $r->id, 'kode' => 'S3-'.$r->kategori,
                             'label' => $r->subkategori, 'kategori' => (string) $r->kategori,
                             'kelompok' => 'Kat. '.$r->kategori.' — '.(Scope3::KATEGORI[$r->kategori] ?? '')],
                'kode' => 'scope3', 'jumlah' => $r->kuantitas, 'satuan' => $r->satuan, 'scope' => 3,
                'kategori_ipcc' => null, 'gj' => null, 'toe' => null, 'co2_fosil_t' => null, 'co2_bio_t' => null,
                'ch4_t' => null, 'n2o_t' => null, 'lain_co2e_t' => null, 'co2e_t' => $t, 'co2e_mb_t' => $t,
                'rupiah' => null, 'persen_fame' => 0.0, 'ketidakpastian_persen' => null, 'faktor_id' => [],
                'kurang' => [], 'catatan' => [], 'n' => 1, 'n_verif' => in_array($r->status, ['terverifikasi', 'terkunci'], true) ? 1 : 0,
                'n_est' => $r->kualitas_data >= 4 ? 1 : 0, 'terakhir' => null,
                /* Kategori 11 — pembakaran batubara terjual — disajikan
                   terpisah [A-10]: angkanya berlipat dari emisi operasi dan
                   akan menenggelamkan target operasional bila dijumlahkan. */
                'terpisah' => $r->kategori === 11,
                'scope3_kategori' => $r->kategori,
            ];
        }

        return $out;
    }

    /* ═══════════════ bantu ═══════════════ */

    private function baris(string $kode, float $jumlah, string $bulan, ?int $company, ?int $site, array $sumber, object $a, array $konteks = []): array
    {
        $tanggal = Carbon::parse($bulan.'-01');
        $atur = $this->pengaturan($company);

        $h = Kalkulator::hitung($kode, $jumlah, $tanggal, $konteks + [
            'company_id' => $company,
            'gwp'        => $this->gwp ?? $atur->gwp_set ?? 'AR5',
            'sumber_data'=> ((int) $a->n_est > 0 && (int) $a->n_est >= (int) $a->n) ? 'estimasi' : 'nota',
        ]);

        foreach ($h['faktor'] as $id => $f) $this->faktor[$id] = $f;

        return [
            'company_id' => $company, 'site_id' => $site, 'bulan' => $bulan, 'sumber' => $sumber,
            'kode' => $kode, 'jumlah' => $jumlah, 'satuan' => $h['satuan'], 'scope' => $h['scope'],
            'kategori_ipcc' => $h['kategori_ipcc'],
            'gj' => $h['gj'], 'toe' => $h['gj'] !== null ? Kalkulator::toe($h['gj'], $tanggal) : null,
            'co2_fosil_t' => $h['co2_fosil_t'], 'co2_bio_t' => $h['co2_bio_t'], 'ch4_t' => $h['ch4_t'],
            'n2o_t' => $h['n2o_t'], 'lain_co2e_t' => $h['lain_co2e_t'], 'co2e_t' => $h['co2e_t'],
            'co2e_mb_t' => $h['co2e_mb_t'], 'rupiah' => $h['rupiah'], 'persen_fame' => $h['persen_fame'],
            'ketidakpastian_persen' => $h['ketidakpastian_persen'], 'faktor_id' => array_keys($h['faktor']),
            'kurang' => $h['kurang'], 'catatan' => $h['catatan'],
            'n' => (int) $a->n, 'n_verif' => (int) $a->n_verif, 'n_est' => (int) $a->n_est,
            'terakhir' => $a->terakhir ? substr((string) $a->terakhir, 0, 10) : null,
            'terpisah' => false,
        ];
    }

    private function pengaturan(?int $company): Pengaturan
    {
        return $this->atur[$company ?? 'null'] ??= Pengaturan::untuk($company);
    }

    /** Sistem grid: milik site bila diisi, lalu pengaturan perusahaan, lalu kosong (faktor nasional). */
    private function wilayah(?int $company, ?int $site): ?string
    {
        if ($site) {
            $this->site[$site] ??= Site::withoutGlobalScopes()->find($site);
            if ($w = $this->site[$site]?->sistem_grid) return $w;
        }

        return $this->pengaturan($company)->sistem_grid;
    }
}
