<?php

namespace App\Support\Energi;

use App\Models\{Company, EnergyBaseline, EnergyEquipment, EnergyFuelLog, EnergyFuelRecon, EnergyOpportunity,
                EnergyOtherLog, EnergyPowerLog, TindakLanjut};
use App\Models\Energi\{Mitigasi, Pengaturan, Site};
use App\Support\{Energi, Perusahaan};
use Illuminate\Support\Carbon;

/**
 * Executive Energy & Carbon Control Tower (PRD §6, FSD §9).
 *
 * Lima pertanyaan direksi, satu layar: (1) on-track terhadap target?
 * (2) site/unit mana yang menyimpang dan berapa rupiahnya? (3) risiko
 * kepatuhan terdekat? (4) eksposur biaya karbon? (5) tindak lanjut apa
 * yang sudah/belum berjalan?
 *
 * Seluruh angka dihitung dari Inventaris yang SAMA dengan laporan, dengan
 * filter yang sama — kartu "Emisi" BOD dan Laporan GRK untuk rentang dan
 * perusahaan yang sama menghasilkan angka yang sama persis (FSD §9.4).
 * Batas perusahaan tetap batas MilikPerusahaan; BOD tidak diberi jalan
 * pintas ke data perusahaan yang tidak dapat dilihatnya.
 */
final class KendaliDireksi
{
    public const PERIODE = ['mtd' => 'Bulan berjalan', 'qtd' => 'Kuartal berjalan', 'ytd' => 'Tahun berjalan',
                            'r12' => '12 bulan terakhir', 'kustom' => 'Kustom'];

    /** @return array{0:Carbon,1:Carbon,2:string} */
    public static function rentang(array $f, ?Carbon $kini = null): array
    {
        $kini ??= now()->startOfDay();
        $p = $f['periode'] ?? 'ytd';

        return match ($p) {
            'mtd'    => [$kini->copy()->startOfMonth(), $kini, 'mtd'],
            'qtd'    => [$kini->copy()->firstOfQuarter(), $kini, 'qtd'],
            'r12'    => [$kini->copy()->subMonths(11)->startOfMonth(), $kini, 'r12'],
            'kustom' => (function () use ($f, $kini) {
                $d = isset($f['dari']) ? Carbon::parse($f['dari'])->startOfDay() : $kini->copy()->startOfYear();
                $s = isset($f['sampai']) ? Carbon::parse($f['sampai'])->startOfDay() : $kini;

                return $d->lte($s) ? [$d, $s, 'kustom'] : [$s, $d, 'kustom'];
            })(),
            default  => [$kini->copy()->startOfYear(), $kini, 'ytd'],
        };
    }

    /** Perusahaan yang boleh dibandingkan pengguna ini — sama dengan yang dapat dilihatnya. */
    public static function perusahaan(): array
    {
        $u = auth()->user();
        if (!$u) return [];

        if ($u->isAdmin()) {
            $pilih = Perusahaan::terpilih($u);

            return Company::withoutGlobalScopes()->when($pilih, fn ($q) => $q->whereKey($pilih))
                ->orderBy('name')->get(['id', 'name'])->map(fn ($c) => ['id' => $c->id, 'nama' => $c->name])->all();
        }

        return $u->company_id ? [['id' => $u->company_id, 'nama' => $u->company?->name ?? 'Perusahaan']] : [];
    }

    public static function saring(array $f): array
    {
        return array_filter([
            'company_id' => isset($f['company']) && $f['company'] !== '' ? (int) $f['company'] : null,
            'site_id'    => isset($f['site']) && $f['site'] !== '' ? (int) $f['site'] : null,
        ], fn ($v) => $v !== null);
    }

    /**
     * Kartu KPI utama (BOD-01, BOD-02, BOD-14) beserta RAG dan pembandingnya.
     */
    public static function kartu(Carbon $dari, Carbon $sampai, array $saring): array
    {
        $inv = Inventaris::hitung($dari, $sampai, $saring);
        $r = Inventaris::ringkas($inv['baris']);
        $p = Inventaris::produksi($dari, $sampai, $saring);

        $lalu = Inventaris::ringkas(Inventaris::hitung($dari->copy()->subYear(), $sampai->copy()->subYear(), $saring)['baris']);
        $pLalu = Inventaris::produksi($dari->copy()->subYear(), $sampai->copy()->subYear(), $saring);

        $b = self::baseline($sampai->year, $saring['company_id'] ?? null);
        $atur = Pengaturan::untuk($saring['company_id'] ?? auth()->user()?->company_id);
        $toleransi = $atur->ambang_rag_persen / 100;

        $rekon = self::rekonsiliasi($dari, $sampai, $saring);

        $bagi = fn ($a, $b) => $b > 0 ? $a / $b : null;
        $mutu = [
            'persen_terverifikasi' => $r['persen_terverifikasi'], 'persen_estimasi' => $r['persen_estimasi'],
            'terakhir' => $r['terakhir'], 'lengkap' => $r['lengkap'],
        ];

        $kartu = [
            self::k('energi', 'Total energi', $r['gj'], 'GJ', null, $lalu['gj'], false, $toleransi,
                    sprintf('%s TOE', number_format($r['toe'], 1, ',', '.'))),
            self::k('gj_ton', 'Intensitas energi', $bagi($r['gj'], $p['ton']), 'GJ/ton', $b?->target_gj_ton,
                    $bagi($lalu['gj'], $pLalu['ton']), false, $toleransi, $b ? 'Target '.number_format((float) $b->target_gj_ton, 4, ',', '.') : 'Belum ada target'),
            self::k('l_bcm', 'Rasio BBM', $bagi($r['liter_solar'], $p['bcm']), 'L/BCM', null,
                    $bagi($lalu['liter_solar'], $pLalu['bcm']), false, $toleransi, null),
            self::k('emisi', 'Emisi Scope 1+2', $r['total'], 'tCO₂e', null, $lalu['total'], false, $toleransi,
                    sprintf('S1 %s · S2 %s · S3 %s', number_format($r['s1'], 0, ',', '.'), number_format($r['s2_lb'], 0, ',', '.'), number_format($r['s3'], 0, ',', '.'))),
            self::k('tco2e_ton', 'Intensitas emisi', $bagi($r['total'], $p['ton']), 'tCO₂e/ton', $b?->target_tco2e_ton,
                    $bagi($lalu['total'], $pLalu['ton']), false, $toleransi, null),
            self::k('biaya', 'Biaya energi', $r['rupiah'], 'Rp', null, $lalu['rupiah'], false, $toleransi,
                    $p['ton'] > 0 ? 'Rp '.number_format($r['rupiah'] / $p['ton'], 0, ',', '.').'/ton' : null),
            self::k('ebt', 'Porsi EBT listrik', $r['persen_ebt'], '%', null, $lalu['persen_ebt'], true, $toleransi, null),
            self::k('rekon', 'Selisih rekonsiliasi BBM', $rekon['persen'], '%', $atur->ambang_rekonsiliasi_persen,
                    null, false, 0.0, $rekon['disalurkan'] > 0 ? number_format($rekon['selisih'], 0, ',', '.').' L' : 'Belum ada rekonsiliasi', true),
        ];

        return ['kartu' => $kartu, 'mutu' => $mutu, 'ringkas' => Pelaporan::potong($r), 'produksi' => $p,
                'baseline' => $b ? ['tahun' => $b->tahun, 'baseline_gj_ton' => $b->baseline_gj_ton, 'target_gj_ton' => $b->target_gj_ton,
                                    'target_tco2e_ton' => $b->target_tco2e_ton] : null,
                'baris' => $inv['baris']];
    }

    /** RAG FSD §9.2 W2: hijau ≤ target, kuning ≤ target + toleransi, merah di atasnya; dibalik untuk yang makin tinggi makin baik. */
    public static function rag(?float $nilai, ?float $target, bool $tinggiBaik, float $toleransi, bool $mutlak = false): string
    {
        if ($nilai === null || $target === null || $target <= 0 && !$mutlak) return 'netral';

        if ($mutlak) {
            $v = abs($nilai);

            return $v <= $target ? 'hijau' : ($v <= $target * 1.5 ? 'kuning' : 'merah');
        }

        if ($tinggiBaik) return $nilai >= $target ? 'hijau' : ($nilai >= $target * (1 - $toleransi) ? 'kuning' : 'merah');

        return $nilai <= $target ? 'hijau' : ($nilai <= $target * (1 + $toleransi) ? 'kuning' : 'merah');
    }

    private static function k(string $kode, string $label, ?float $nilai, string $satuan, ?float $target, ?float $lalu,
                              bool $tinggiBaik, float $tol, ?string $catatan, bool $mutlak = false): array
    {
        $arah = null;
        if ($nilai !== null && $lalu !== null && $lalu != 0.0) {
            $ubah = ($nilai - $lalu) / abs($lalu) * 100;
            $arah = ['persen' => $ubah, 'baik' => $tinggiBaik ? $ubah >= 0 : $ubah <= 0];
        }

        return ['kode' => $kode, 'label' => $label, 'nilai' => $nilai, 'satuan' => $satuan, 'target' => $target,
                'lalu' => $lalu, 'arah' => $arah, 'rag' => self::rag($nilai, $target, $tinggiBaik, $tol, $mutlak),
                'catatan' => $catatan, 'tinggi_baik' => $tinggiBaik];
    }

    public static function baseline(int $tahun, ?int $company): ?EnergyBaseline
    {
        $q = EnergyBaseline::query()->where('status', 'disetujui_bod')->where('tahun', '<=', $tahun)->orderByDesc('tahun');

        return ($company ? (clone $q)->where('company_id', $company)->first() : null) ?? $q->first();
    }

    /**
     * Rekonsiliasi BBM: terpakai menurut stok tangki dibandingkan liter yang
     * tercatat per unit & genset — HANYA pada tanggal yang punya
     * rekonsiliasi. Menjumlah catatan sepanjang rentang sementara
     * rekonsiliasinya hanya beberapa hari menghasilkan selisih negatif
     * ratusan persen yang tidak berarti apa-apa.
     */
    public static function rekonsiliasi(Carbon $dari, Carbon $sampai, array $saring): array
    {
        $rekon = EnergyFuelRecon::query()->whereBetween('tanggal', Inventaris::sela($dari, $sampai))
            ->when($saring['company_id'] ?? null, fn ($q, $c) => $q->where('company_id', $c))->get();
        $disalurkan = $rekon->sum(fn ($x) => $x->terpakaiMenurutStok());
        $hari = $rekon->map(fn ($x) => $x->tanggal->toDateString())->unique()->flip();

        $perHari = fn ($q, string $kolom) => $q->whereBetween('tanggal', Inventaris::sela($dari, $sampai))
            ->selectRaw("tanggal, SUM({$kolom}) as jumlah")->groupBy('tanggal')->get()
            ->filter(fn ($b) => $hari->has(substr((string) $b->getRawOriginal('tanggal'), 0, 10)))
            ->sum(fn ($b) => (float) $b->jumlah);

        $tercatat = $hari->isEmpty() ? 0.0
            : $perHari(EnergyFuelLog::query()->when($saring['company_id'] ?? null,
                fn ($q, $c) => $q->whereHas('equipment', fn ($e) => $e->where('company_id', $c))), 'liter')
            + $perHari(EnergyPowerLog::query()->when($saring['company_id'] ?? null, fn ($q, $c) => $q->where('company_id', $c)), 'liter_genset');

        return ['disalurkan' => $disalurkan, 'tercatat' => $tercatat, 'selisih' => $disalurkan - $tercatat,
                'hari' => $hari->count(),
                'persen' => $disalurkan > 0 ? Energi::selisihRekonsiliasi($disalurkan, $tercatat) : null];
    }

    /**
     * Tren bulanan aktual vs baseline vs target + proyeksi akhir tahun (BOD-04).
     */
    public static function tren(array $baris, Carbon $dari, Carbon $sampai, array $saring, ?array $baseline): array
    {
        $perBulan = Inventaris::kelompok($baris, fn ($b) => $b['bulan']);
        $bulan = [];
        for ($m = $dari->copy()->startOfMonth(); $m->lte($sampai); $m->addMonth()) $bulan[] = $m->format('Y-m');

        $deret = [];
        foreach ($bulan as $b) {
            $awal = Carbon::parse($b.'-01');
            $p = Inventaris::produksi($awal->copy()->max($dari), $awal->copy()->endOfMonth()->min($sampai), $saring);
            $r = $perBulan[$b] ?? null;
            $deret[] = ['bulan' => $b, 'label' => $awal->translatedFormat('M y'),
                        'gj' => $r['gj'] ?? 0.0, 'tco2e' => $r['total'] ?? 0.0, 'ton' => $p['ton'],
                        'gj_ton' => $p['ton'] > 0 ? ($r['gj'] ?? 0) / $p['ton'] : null,
                        'tco2e_ton' => $p['ton'] > 0 ? ($r['total'] ?? 0) / $p['ton'] : null];
        }

        /* Proyeksi akhir tahun: YTD + run-rate 3 bulan terakhir × sisa bulan,
           pita ±1σ dari deret bulanan (FSD §9.2 W3). Bulan berjalan yang
           belum genap tidak ikut run-rate — ia menarik rerata ke bawah. */
        $tahun = $sampai->year;
        $ytd = array_filter($deret, fn ($d) => str_starts_with($d['bulan'], (string) $tahun));
        $penuh = array_values(array_filter($ytd, fn ($d) => $d['bulan'] < now()->format('Y-m')));
        $akhir = array_slice($penuh, -3);
        $sisa = 12 - (int) $sampai->format('n') + ($sampai->isLastOfMonth() ? 0 : 1);

        $proyeksi = null;
        if ($akhir) {
            $rate = array_sum(array_column($akhir, 'tco2e')) / count($akhir);
            $rateGj = array_sum(array_column($akhir, 'gj')) / count($akhir);
            $vals = array_column($penuh, 'tco2e');
            $rata = array_sum($vals) / max(1, count($vals));
            $sigma = count($vals) > 1 ? sqrt(array_sum(array_map(fn ($v) => ($v - $rata) ** 2, $vals)) / (count($vals) - 1)) : 0.0;
            $sudah = array_sum(array_column($ytd, 'tco2e'));
            $proyeksi = ['tco2e' => $sudah + $rate * $sisa, 'gj' => array_sum(array_column($ytd, 'gj')) + $rateGj * $sisa,
                         'pita' => $sigma * sqrt(max(1, $sisa)), 'sisa_bulan' => $sisa];
        }

        return ['deret' => $deret, 'proyeksi' => $proyeksi,
                'baseline_gj_ton' => $baseline['baseline_gj_ton'] ?? null, 'target_gj_ton' => $baseline['target_gj_ton'] ?? null];
    }

    /** Peta panas kepatuhan per perusahaan (BOD-06). */
    public static function petaKepatuhan(array $perusahaan): array
    {
        return array_map(fn ($c) => ['id' => $c['id'], 'nama' => $c['nama'], 'kewajiban' => Kewajiban::daftar($c['id'])],
            array_slice($perusahaan, 0, 12));
    }

    /** Peringkat unit boros & rupiah deviasinya (BOD-12, W6). */
    public static function peringkatUnit(Carbon $dari, Carbon $sampai, array $saring, int $batas = 10): array
    {
        $agg = EnergyFuelLog::query()->whereBetween('tanggal', Inventaris::sela($dari, $sampai))
            ->when($saring['company_id'] ?? null, fn ($q, $c) => $q->whereHas('equipment', fn ($e) => $e->where('company_id', $c)))
            ->when($saring['site_id'] ?? null, fn ($q, $s) => $q->whereHas('equipment', fn ($e) => $e->where('site_id', $s)))
            ->selectRaw('equipment_id, SUM(liter) as liter, SUM(hm) as hm')->groupBy('equipment_id')->get();
        if ($agg->isEmpty()) return [];

        $unit = EnergyEquipment::withoutGlobalScopes()->whereIn('id', $agg->pluck('equipment_id'))->get()->keyBy('id');

        /* Acuan per perusahaan DAN kategori. Pada tampilan grup, rata-rata
           kategori lintas perusahaan membandingkan armada yang berbeda
           ukuran dan medan — yang terlihat boros hanyalah perusahaan
           dengan alat lebih besar. Kode unit pun berulang antarperusahaan,
           jadi singkatan perusahaannya ikut disebut. */
        $banyak = $unit->pluck('company_id')->unique()->count() > 1;
        $singkat = $banyak ? Company::withoutGlobalScopes()->whereIn('id', $unit->pluck('company_id')->filter()->unique())
            ->get()->mapWithKeys(fn ($c) => [$c->id => $c->code ?: $c->nama_pendek ?: $c->name]) : collect();
        $kunci = fn ($u) => ($u->company_id ?? 0).'|'.$u->kategori;

        $acuan = [];
        foreach ($agg as $a) {
            $u = $unit[$a->equipment_id] ?? null;
            if (!$u || $a->hm <= 0) continue;
            $acuan[$kunci($u)][] = [$a->liter, $a->hm];
        }
        $acuan = array_map(fn ($xs) => array_sum(array_column($xs, 1)) > 0
            ? array_sum(array_column($xs, 0)) / array_sum(array_column($xs, 1)) : 0.0, $acuan);

        $harga = RegistriFaktor::nilai('solar', 'harga', $sampai, ['company_id' => $saring['company_id'] ?? auth()->user()?->company_id])
              ?? Energi::RP_PER_LITER;

        $out = [];
        foreach ($agg as $a) {
            $u = $unit[$a->equipment_id] ?? null;
            if (!$u || $a->hm <= 0) continue;
            $lhm = $a->liter / $a->hm;
            $ac = $acuan[$kunci($u)] ?? 0.0;
            $out[] = ['id' => $u->id, 'kode' => $banyak ? $u->kode.' · '.($singkat[$u->company_id] ?? '—') : $u->kode, 'nama' => $u->nama, 'kategori' => Energi::KATEGORI[$u->kategori] ?? $u->kategori,
                      'l_hm' => $lhm, 'acuan' => $ac, 'liter' => (float) $a->liter,
                      'deviasi_rp' => max(0.0, ($lhm - $ac) * $a->hm * $harga),
                      'status' => Energi::statusEfisiensi($lhm, $ac)];
        }
        usort($out, fn ($x, $y) => $y['deviasi_rp'] <=> $x['deviasi_rp']);

        return array_slice($out, 0, $batas);
    }

    /** Pusat peringatan (BOD-09). */
    public static function peringatan(Carbon $sampai, array $saring, array $kewajiban, array $kartu): array
    {
        $out = [];
        $c = $saring['company_id'] ?? null;

        foreach (array_slice(Anomali::bbm($sampai, 30, $c), 0, 8) as $a) {
            $out[] = ['tingkat' => 'merah', 'jenis' => 'Anomali konsumsi', 'judul' => $a['unit'].' — '.$a['pesan'],
                      'tanggal' => $a['tanggal'], 'tautan' => route('energi.equipment.show', $a['unit_id'])];
        }

        $batas = now()->subDays(3)->toDateString();
        $tertahan = 0;
        foreach ([EnergyFuelLog::class, EnergyPowerLog::class, EnergyOtherLog::class] as $kelas) {
            $q = $kelas::query()->where('status', 'diajukan')->whereDate('tanggal', '<', $batas);
            if ($c) $q = $kelas === EnergyFuelLog::class ? $q->whereHas('equipment', fn ($e) => $e->where('company_id', $c)) : $q->where('company_id', $c);
            $tertahan += $q->count();
        }
        if ($tertahan) {
            $out[] = ['tingkat' => 'kuning', 'jenis' => 'Data belum terverifikasi', 'judul' => "{$tertahan} baris diajukan lebih dari 3 hari belum diverifikasi.",
                      'tanggal' => null, 'tautan' => route('energi.periode.index')];
        }

        foreach ($kewajiban as $k) {
            if ($k['status'] === 'terlambat') {
                $out[] = ['tingkat' => 'merah', 'jenis' => 'Kepatuhan', 'judul' => $k['judul'].' — terlambat', 'tanggal' => $k['tenggat'],
                          'tautan' => route('energi.kalender')];
            } elseif (in_array($k['status'], ['belum', 'proses'], true) && $k['sisa_hari'] !== null && $k['sisa_hari'] <= ($k['kode'] === 'SERT_MANAJER' ? 60 : 30)) {
                $out[] = ['tingkat' => 'kuning', 'jenis' => 'Kepatuhan', 'judul' => $k['judul'].' — '.$k['sisa_hari'].' hari lagi',
                          'tanggal' => $k['tenggat'], 'tautan' => route('energi.kalender')];
            }
        }

        foreach ($kartu as $k) {
            if ($k['kode'] === 'rekon' && $k['rag'] === 'merah') {
                $out[] = ['tingkat' => 'merah', 'jenis' => 'Rekonsiliasi BBM', 'judul' => 'Selisih rekonsiliasi '.number_format((float) $k['nilai'], 1, ',', '.').'% melebihi ambang.',
                          'tanggal' => null, 'tautan' => route('energi.fuel')];
            }
        }

        usort($out, fn ($a, $b) => [$a['tingkat'] === 'merah' ? 0 : 1] <=> [$b['tingkat'] === 'merah' ? 0 : 1]);

        return $out;
    }

    /** Portofolio inisiatif hemat (BOD-10). */
    public static function portofolio(array $saring): array
    {
        $o = EnergyOpportunity::query()->when($saring['company_id'] ?? null, fn ($q, $c) => $q->where('company_id', $c))->get();

        $per = [];
        foreach (Energi::STATUS_PELUANG as $s) {
            $g = $o->where('status', $s);
            $per[] = ['status' => $s, 'jumlah' => $g->count(), 'capex_rp' => (float) $g->sum('capex_rp'),
                      'rencana_gj' => $g->sum(fn ($x) => $x->gj()) * 12, 'rencana_tco2e' => $g->sum(fn ($x) => $x->tco2e()) * 12,
                      'rencana_rp' => $g->sum(fn ($x) => $x->rupiah()) * 12,
                      'terverifikasi_tco2e' => $g->sum(fn ($x) => $x->terverifikasi() ? $x->tco2eTerverifikasi() * 12 : 0)];
        }

        $rinci = $o->sortByDesc(fn ($x) => $x->tco2e())->take(12)->map(fn ($x) => [
            'id' => $x->id, 'judul' => $x->judul, 'status' => $x->status, 'pic' => $x->penanggung_jawab,
            'capex_rp' => $x->capex_rp, 'payback' => $x->payback(), 'rencana_tco2e' => $x->tco2e() * 12,
            'terverifikasi_tco2e' => $x->terverifikasi() ? $x->tco2eTerverifikasi() * 12 : null,
            'metode_mv' => $x->metode_mv,
        ])->values()->all();

        return ['per_status' => $per, 'rincian' => $rinci];
    }

    /** Eksposur biaya karbon per skenario harga (BOD-07). */
    public static function eksposur(float $tco2e, Carbon $tanggal, ?int $company, ?float $kustom = null): array
    {
        $out = [];
        foreach (['harga_dasar' => 'Dasar', 'harga_rendah' => 'Rendah', 'harga_tinggi' => 'Tinggi'] as $p => $l) {
            $f = RegistriFaktor::ambil('karbon', $p, $tanggal, ['company_id' => $company]);
            if ($f) $out[] = ['skenario' => $l, 'harga' => $f->nilai, 'rupiah' => $tco2e * $f->nilai, 'sumber' => $f->sumber_dokumen];
        }
        if ($kustom !== null && $kustom > 0) {
            $out[] = ['skenario' => 'Kustom', 'harga' => $kustom, 'rupiah' => $tco2e * $kustom, 'sumber' => 'Isian pengguna'];
        }

        return $out;
    }

    /**
     * Simulator skenario what-if (BOD-08). Tidak menyimpan apa pun.
     *
     * @param array{produksi?:float,fame?:?float,pln?:float,genset?:float,plts?:float,harga_solar?:float,l_bcm?:float} $s
     */
    public static function simulasi(array $ring, Carbon $tanggal, ?int $company, array $s): array
    {
        $kProd = 1 + (float) ($s['produksi'] ?? 0) / 100;
        $kLbcm = 1 + (float) ($s['l_bcm'] ?? 0) / 100;
        $kHarga = 1 + (float) ($s['harga_solar'] ?? 0) / 100;
        $k = ['company_id' => $company, 'gwp' => Pengaturan::untuk($company)->gwp_set];

        $hitung = function (float $liter, float $kwhPln, float $literGenset, float $kwhEbt, ?float $fame, float $kHargaSolar) use ($tanggal, $k) {
            $a = Kalkulator::hitung('solar', $liter, $tanggal, $k + ['persen_fame' => $fame]);
            $g = Kalkulator::hitung('solar', $literGenset, $tanggal, $k + ['persen_fame' => $fame, 'kategori_ipcc' => 'stasioner']);
            $l = Kalkulator::hitung('listrik_pln', $kwhPln, $tanggal, $k);
            $e = Kalkulator::hitung('plts', $kwhEbt, $tanggal, $k);

            return [
                'gj' => (float) $a['gj'] + (float) $g['gj'] + (float) $l['gj'] + (float) $e['gj'],
                'tco2e' => (float) $a['co2e_t'] + (float) $g['co2e_t'] + (float) $l['co2e_t'],
                'rupiah' => ((float) $a['rupiah'] + (float) $g['rupiah']) * $kHargaSolar + (float) $l['rupiah'],
            ];
        };

        $kwhTotal = $ring['kwh_beli'] + $ring['kwh_genset'] + $ring['kwh_ebt'];
        $literGenset = (float) ($ring['liter_genset'] ?? 0);
        $literAlat = max(0.0, $ring['liter_solar'] - $literGenset);
        // kWh per liter genset dari data periode ini; 3,5 bila belum ada data genset.
        $effGenset = $literGenset > 0 && $ring['kwh_genset'] > 0 ? $ring['kwh_genset'] / $literGenset : 3.5;

        $dasar = $hitung($literAlat, $ring['kwh_beli'], $literGenset, $ring['kwh_ebt'], null, 1.0);

        $mixTotal = (float) ($s['pln'] ?? 0) + (float) ($s['genset'] ?? 0) + (float) ($s['plts'] ?? 0);
        $kwhBaru = $kwhTotal * $kProd;
        [$pln, $literGen, $ebt] = $mixTotal > 0
            ? [$kwhBaru * $s['pln'] / $mixTotal, $kwhBaru * $s['genset'] / $mixTotal / $effGenset, $kwhBaru * $s['plts'] / $mixTotal]
            : [$ring['kwh_beli'] * $kProd, $literGenset * $kProd, $ring['kwh_ebt'] * $kProd];

        $skenario = $hitung($literAlat * $kProd * $kLbcm, $pln, $literGen, $ebt,
            isset($s['fame']) && $s['fame'] !== '' && $s['fame'] !== null ? (float) $s['fame'] : null, $kHarga);

        return ['dasar' => $dasar, 'skenario' => $skenario,
                'selisih' => ['gj' => $skenario['gj'] - $dasar['gj'], 'tco2e' => $skenario['tco2e'] - $dasar['tco2e'],
                              'rupiah' => $skenario['rupiah'] - $dasar['rupiah']]];
    }

    /** Jalur dekarbonisasi: emisi tahunan vs lintasan target + kontribusi inisiatif (BOD-05). */
    public static function jalur(?int $company): array
    {
        $atur = Pengaturan::untuk($company);
        $kini = now()->year;
        $dasar = $atur->tahun_dasar ?: $kini - 3;
        $saring = $company ? ['company_id' => $company] : [];

        $tahun = [];
        foreach (range($dasar, $kini) as $t) {
            $r = Inventaris::ringkas(Inventaris::hitung("{$t}-01-01", min("{$t}-12-31", now()->toDateString()), $saring)['baris']);
            /* Tahun berjalan diproyeksikan setahun penuh menurut laju
               hariannya. Angka berjalan apa adanya selalu tampak anjlok
               dibanding tahun penuh sebelumnya — penurunan yang hanya
               berarti tahunnya belum selesai. */
            if ($t === $kini) {
                $lewat = max(1, now()->dayOfYear);
                $tahun[] = ['tahun' => $t, 'tco2e' => $r['total'] * (now()->daysInYear / $lewat), 'ytd' => $r['total'], 'proyeksi' => true];
            } else {
                $tahun[] = ['tahun' => $t, 'tco2e' => $r['total']];
            }
        }

        $lintasan = [];
        if ($atur->target_tahun && $atur->target_penurunan_persen !== null && $tahun) {
            $awal = $tahun[0]['tco2e'];
            $akhir = $awal * (1 - $atur->target_penurunan_persen / 100);
            $rentang = max(1, $atur->target_tahun - $dasar);
            foreach (range($dasar, $atur->target_tahun) as $t) {
                $lintasan[] = ['tahun' => $t, 'tco2e' => $awal + ($akhir - $awal) * ($t - $dasar) / $rentang];
            }
        }

        $kontribusi = Mitigasi::query()->when($company, fn ($q) => $q->where('company_id', $company))->get()
            ->map(fn ($m) => ['judul' => $m->judul, 'tco2e' => (float) $m->target_penurunan_tco2e_tahun, 'capaian' => $m->capaianTahun($kini)])
            ->filter(fn ($m) => $m['tco2e'] > 0)->sortByDesc('tco2e')->values()->all();

        return ['tahun' => $tahun, 'lintasan' => $lintasan, 'kontribusi' => $kontribusi,
                'target' => ['tahun' => $atur->target_tahun, 'persen' => $atur->target_penurunan_persen, 'dasar' => $dasar]];
    }

    /** Instruksi BOD terbuka — TindakLanjut bermodul energi (BOD-11). */
    public static function instruksi(array $saring): array
    {
        return TindakLanjut::query()->modul('energi')->where('kategori', 'instruksi_bod')
            ->when($saring['company_id'] ?? null, fn ($q, $c) => $q->where('company_id', $c))
            ->urutMendesak()->limit(30)->get()->map(fn (TindakLanjut $t) => $t->toView())->values()->all();
    }

    /** Benchmark antar-perusahaan: intensitas & emisi (BOD-12). */
    public static function benchmark(array $perusahaan, Carbon $dari, Carbon $sampai): array
    {
        $out = [];
        foreach (array_slice($perusahaan, 0, 12) as $c) {
            $s = ['company_id' => $c['id']];
            $r = Inventaris::ringkas(Inventaris::hitung($dari, $sampai, $s)['baris']);
            $p = Inventaris::produksi($dari, $sampai, $s);
            $out[] = ['id' => $c['id'], 'nama' => $c['nama'], 'gj' => $r['gj'], 'tco2e' => $r['total'],
                      'gj_ton' => $p['ton'] > 0 ? $r['gj'] / $p['ton'] : null,
                      'tco2e_ton' => $p['ton'] > 0 ? $r['total'] / $p['ton'] : null];
        }
        usort($out, fn ($a, $b) => ($a['gj_ton'] ?? INF) <=> ($b['gj_ton'] ?? INF));

        return $out;
    }

    /**
     * Drill-down (BOD-03): grup → perusahaan → site → kelompok → sumber → catatan harian.
     */
    public static function drill(array $baris, string $tingkat, ?string $kunci, Carbon $dari, Carbon $sampai): array
    {
        $saring = fn (callable $f) => array_values(array_filter($baris, $f));

        return match ($tingkat) {
            'perusahaan' => self::anak($baris, fn ($b) => (string) ($b['company_id'] ?? 0), fn ($k) =>
                Company::withoutGlobalScopes()->find((int) $k)?->name ?? 'Tanpa perusahaan', 'site'),
            'site' => self::anak($saring(fn ($b) => (string) ($b['company_id'] ?? 0) === (string) $kunci),
                fn ($b) => ($b['company_id'] ?? 0).':'.($b['site_id'] ?? 0),
                fn ($k) => Site::withoutGlobalScopes()->find((int) explode(':', $k)[1])?->nama ?? 'Tanpa site', 'kelompok'),
            'kelompok' => self::anak($saring(fn ($b) => ($b['company_id'] ?? 0).':'.($b['site_id'] ?? 0) === $kunci),
                fn ($b) => $kunci.':'.$b['sumber']['jenis'].':'.$b['sumber']['kategori'],
                fn ($k) => null, 'sumber', fn ($b) => $b['sumber']['kelompok']),
            'sumber' => self::anak($saring(fn ($b) => $kunci === ($b['company_id'] ?? 0).':'.($b['site_id'] ?? 0).':'.$b['sumber']['jenis'].':'.$b['sumber']['kategori']),
                fn ($b) => $b['sumber']['jenis'].':'.($b['sumber']['id'] ?? $b['sumber']['kode']),
                fn ($k) => null, 'harian', fn ($b) => $b['sumber']['label']),
            'harian' => self::harian($kunci, $dari, $sampai),
            default => self::anak($baris, fn ($b) => 'grup', fn () => 'Seluruh perusahaan', 'perusahaan'),
        };
    }

    private static function anak(array $baris, callable $kunci, callable $label, string $berikut, ?callable $labelBaris = null): array
    {
        $g = [];
        foreach ($baris as $b) {
            $k = (string) $kunci($b);
            $g[$k]['baris'][] = $b;
            $g[$k]['label'] ??= $labelBaris ? $labelBaris($b) : null;
        }

        $out = [];
        foreach ($g as $k => $isi) {
            $r = Inventaris::ringkas($isi['baris']);
            $out[] = ['kunci' => $k, 'label' => $isi['label'] ?? $label($k), 'berikut' => $berikut,
                      'gj' => $r['gj'], 'tco2e' => $r['total'] + $r['s3'], 'rupiah' => $r['rupiah'],
                      'persen_terverifikasi' => $r['persen_terverifikasi'], 'lengkap' => $r['lengkap']];
        }
        usort($out, fn ($a, $b) => $b['tco2e'] <=> $a['tco2e']);

        return $out;
    }

    /** Catatan harian satu unit alat, lengkap dengan faktor yang dipakai tiap barisnya. */
    private static function harian(?string $kunci, Carbon $dari, Carbon $sampai): array
    {
        [$jenis, $id] = array_pad(explode(':', (string) $kunci, 2), 2, null);
        if ($jenis !== 'alat' || !$id) return [];

        $unit = EnergyEquipment::find((int) $id);
        if (!$unit) return [];

        return EnergyFuelLog::where('equipment_id', $unit->id)
            ->whereBetween('tanggal', Inventaris::sela($dari, $sampai))->orderBy('tanggal')->get()
            ->map(function (EnergyFuelLog $l) use ($unit) {
                $h = Kalkulator::hitung('solar', $l->liter, $l->tanggal, [
                    'company_id' => $unit->company_id, 'kategori_ipcc' => $unit->kategori_ipcc, 'persen_fame' => $l->persen_fame,
                    'sumber_data' => $l->sumber_data]);

                return ['kunci' => 'log:'.$l->id, 'label' => $l->tanggal->translatedFormat('d M Y'), 'berikut' => null,
                        'liter' => $l->liter, 'hm' => $l->hm, 'status' => $l->status, 'sumber_data' => $l->sumber_data,
                        'gj' => $h['gj'], 'tco2e' => $h['co2e_t'], 'rupiah' => $h['rupiah'], 'persen_fame' => $h['persen_fame'],
                        'faktor' => array_values($h['faktor'])];
            })->all();
    }
}
