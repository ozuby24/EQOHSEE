<?php

namespace App\Support\Lca;

use App\Models\{EnergyEquipment, EnergyFuelLog, EnergyOtherLog, EnergyPowerLog, EnergyProduction};
use App\Models\Lca\{Faktor, Hasil, Inventori, Skenario, Studi};
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mesin LCI/LCIA (FSD EcoTrace §4), satu tempat untuk seluruh angka:
 *
 *   Q_normal = Q_masukan × konversi(satuan → satuan faktor)
 *              (tahap angkutan berfaktor t·km: massa ton × jarak km)
 *   I_c      = Q_normal × alokasi% × (1 − 0,65 × daur ulang%) × EF_c
 *   DQR_i    = rata-rata lima indikator pedigree (1 terbaik – 5)
 *   DQR      = Σ DQR_i·|GWP_i| / Σ |GWP_i|
 *   u%       = 5 + (DQR − 1) × 8,5
 *
 * Hasil per kategori per tahap disimpan ke lca_hasil sebagai tembolok
 * yang dihitung ulang setiap kali inventorinya berubah.
 */
final class Mesin
{
    public const KOLOM = ['gwp', 'ap', 'ep', 'wsf', 'ced', 'odp'];

    public static function pedigree(array $p): array
    {
        $nilai = array_map(fn ($k) => max(1, min(5, (int) ($p[$k] ?? 2))), array_keys(Katalog::PEDIGREE));
        $dqr = round(array_sum($nilai) / 5, 2);

        return ['dqr' => $dqr, 'ketidakpastian' => round(5 + ($dqr - 1) * 8.5, 2), 'mutu' => Katalog::mutu($dqr)];
    }

    /** Hitung satu baris tanpa menyimpan. */
    public static function pratinjau(Faktor $f, array $d): array
    {
        $q = (float) ($d['jumlah'] ?? 0);
        $satuan = (string) ($d['satuan'] ?? $f->satuan);
        $jarak = (float) ($d['jarak_km'] ?? 0);

        if ($f->satuan === 'tkm' && $jarak > 0 && Katalog::satuanGrup($satuan) === 'massa') {
            $normal = Katalog::konversi($q, $satuan, 'ton') * $jarak;
        } else {
            $normal = Katalog::konversi($q, $satuan, $f->satuan);
        }

        $alokasi = max(0, min(100, (float) ($d['alokasi_pct'] ?? 100))) / 100;
        $daur = 1 - (max(0, min(100, (float) ($d['daur_ulang_pct'] ?? 0))) / 100) * 0.65;
        $efektif = $normal * $alokasi * $daur;

        $hasil = ['jumlah_normal' => round($normal, 6), 'satuan_faktor' => $f->satuan];
        foreach (self::KOLOM as $k) {
            $hasil['f_'.$k] = $f->{$k};
            $hasil[$k] = round($efektif * $f->{$k}, $k === 'odp' ? 12 : 6);
        }

        return $hasil + self::pedigree($d);
    }

    public static function simpanItem(Studi $s, array $d): Inventori
    {
        return DB::transaction(function () use ($s, $d) {
            $f = Faktor::query()->findOrFail($d['faktor_id']);
            $h = self::pratinjau($f, $d);

            $item = Inventori::withoutGlobalScopes()->create([
                'company_id' => $s->company_id, 'studi_id' => $s->id, 'faktor_id' => $f->id,
                'tahap' => $d['tahap'] ?? $f->tahap_bawaan, 'nama' => ($d['nama'] ?? null) ?: $f->nama,
                'jumlah' => $d['jumlah'], 'satuan' => $d['satuan'] ?? $f->satuan, 'jumlah_normal' => $h['jumlah_normal'],
                'jarak_km' => $d['jarak_km'] ?? null, 'daur_ulang_pct' => $d['daur_ulang_pct'] ?? 0, 'alokasi_pct' => $d['alokasi_pct'] ?? 100,
                ...collect($h)->only(['f_gwp', 'f_ap', 'f_ep', 'f_wsf', 'f_ced', 'f_odp', ...self::KOLOM, 'dqr', 'ketidakpastian'])->all(),
                ...collect($d)->only(array_keys(Katalog::PEDIGREE))->map(fn ($v) => (int) $v)->all(),
                'sumber_data' => $d['sumber_data'] ?? 'primer', 'catatan' => $d['catatan'] ?? null,
            ]);
            self::hitungUlang($s);

            return $item;
        });
    }

    /** Agregasi ulang enam kategori per tahap dan total studi. */
    public static function hitungUlang(Studi $s): Studi
    {
        $items = Inventori::withoutGlobalScopes()->where('studi_id', $s->id)->get();
        $total = [];
        foreach (self::KOLOM as $k) $total[$k] = (float) $items->sum($k);

        foreach (Katalog::DAMPAK as $k => $meta) {
            foreach (array_keys(Katalog::TAHAP) as $tahap) {
                $v = (float) $items->where('tahap', $tahap)->sum($k);
                Hasil::withoutGlobalScopes()->updateOrCreate(['studi_id' => $s->id, 'kategori' => $k, 'tahap' => $tahap], [
                    'company_id' => $s->company_id, 'nilai' => $v, 'satuan' => $meta['satuan'],
                    'porsi' => abs($total[$k]) > 0 ? round($v / $total[$k] * 100, 2) : 0,
                    'normal' => round($v / $meta['normal'], 12),
                ]);
            }
        }

        $absGwp = $items->sum(fn ($i) => abs($i->gwp));
        $s->forceFill([
            'total_gwp' => $total['gwp'], 'total_ap' => $total['ap'], 'total_ep' => $total['ep'],
            'total_wsf' => $total['wsf'], 'total_ced' => $total['ced'], 'total_odp' => $total['odp'],
            'dqr' => $absGwp > 0 ? round($items->sum(fn ($i) => $i->dqr * abs($i->gwp)) / $absGwp, 2) : null,
        ])->save();

        return $s;
    }

    /** Lima kontributor GWP terbesar (Pareto) beserta porsi kumulatif. */
    public static function hotspot(Studi $s, int $n = 8): array
    {
        $items = Inventori::withoutGlobalScopes()->where('studi_id', $s->id)->where('gwp', '>', 0)->orderByDesc('gwp')->get();
        $total = max(1e-9, (float) $items->sum('gwp'));
        $jalan = 0;

        return $items->take($n)->map(function (Inventori $i) use ($total, &$jalan) {
            $jalan += $i->gwp;
            return ['id' => $i->id, 'nama' => $i->nama, 'tahap' => $i->tahap, 'gwp' => $i->gwp,
                'porsi' => round($i->gwp / $total * 100, 1), 'kumulatif' => round($jalan / $total * 100, 1), 'dqr' => $i->dqr];
        })->values()->all();
    }

    /**
     * Simulasi dekarbonisasi lima tuas, dihitung per BARIS inventori
     * menurut kategori faktornya — bukan potongan rata per tahap:
     *
     *   ebt        : x% listrik grid/captive diganti PLTS (0,045 kg/kWh)
     *   bbm        : x% penghematan solar/bensin alat & kendaraan (maks 30%)
     *   logistik   : x% penurunan emisi angkutan & pengapalan (maks 40%)
     *   daur_ulang : x% substitusi material/peledak/kemasan, efek 50% (maks 60%)
     *   limbah     : x% limbah dialihkan dari TPA/insinerator, efek 65% (maks 90%)
     */
    public static function simulasi(Studi $s, array $t): array
    {
        $ebt = max(0, min(100, (float) ($t['ebt'] ?? 0))) / 100;
        $bbm = max(0, min(30, (float) ($t['bbm'] ?? 0))) / 100;
        $log = max(0, min(40, (float) ($t['logistik'] ?? 0))) / 100;
        $dau = max(0, min(60, (float) ($t['daur_ulang'] ?? 0))) / 100;
        $lim = max(0, min(90, (float) ($t['limbah'] ?? 0))) / 100;

        $items = Inventori::withoutGlobalScopes()->where('studi_id', $s->id)->with(['faktor' => fn ($q) => $q->withoutGlobalScopes()])->get();
        $tahap = array_fill_keys(array_keys(Katalog::TAHAP), ['baseline' => 0.0, 'proyeksi' => 0.0]);
        $tuas = ['ebt' => 0.0, 'bbm' => 0.0, 'logistik' => 0.0, 'daur_ulang' => 0.0, 'limbah' => 0.0];

        foreach ($items as $i) {
            $f = $i->faktor;
            $kat = $f?->kategori;
            $g = $i->gwp;
            $p = $g;
            if ($kat === 'energi' && $f->satuan === 'kWh' && $f->kode !== 'EF-EL-PLTS' && $i->f_gwp > 0) {
                $p = $g * (1 - $ebt * (1 - 0.045 / $i->f_gwp)); $tuas['ebt'] += $g - $p;
            } elseif ($kat === 'energi' && in_array($f->satuan, ['L'], true)) {
                $p = $g * (1 - $bbm); $tuas['bbm'] += $g - $p;
            } elseif ($kat === 'transportasi') {
                $p = $g * (1 - $log); $tuas['logistik'] += $g - $p;
            } elseif (in_array($kat, ['material', 'peledak', 'kemasan'], true) && $g > 0) {
                $p = $g * (1 - $dau * 0.5); $tuas['daur_ulang'] += $g - $p;
            } elseif ($kat === 'limbah' && $g > 0) {
                $p = $g * (1 - $lim * 0.65); $tuas['limbah'] += $g - $p;
            }
            $tahap[$i->tahap]['baseline'] += $g;
            $tahap[$i->tahap]['proyeksi'] += $p;
        }

        $base = array_sum(array_column($tahap, 'baseline'));
        $proy = array_sum(array_column($tahap, 'proyeksi'));
        $delta = max(0, $base - $proy);
        $tonTahun = round($delta * $s->volume_tahunan / 1000, 2);

        return [
            'baseline' => round($base, 4), 'proyeksi' => round($proy, 4), 'delta' => round($delta, 4),
            'reduksi_pct' => $base > 0 ? round($delta / $base * 100, 2) : 0,
            'hemat_t_tahun' => $tonTahun,
            'hemat_idr' => round($tonTahun * Katalog::HARGA['idr_per_t']),
            'hemat_eur' => round($tonTahun * Katalog::HARGA['eur_per_t']),
            'tahap' => collect($tahap)->map(fn ($v, $k) => ['tahap' => $k, 'baseline' => round($v['baseline'], 4), 'proyeksi' => round($v['proyeksi'], 4)])->values()->all(),
            'tuas' => collect($tuas)->map(fn ($v) => round($v, 4))->all(),
        ];
    }

    public static function simpanSkenario(Studi $s, string $nama, array $t, ?string $ket = null): Skenario
    {
        $h = self::simulasi($s, $t);

        return Skenario::withoutGlobalScopes()->create([
            'company_id' => $s->company_id, 'studi_id' => $s->id, 'nama' => $nama, 'deskripsi' => $ket,
            'tuas_ebt' => $t['ebt'] ?? 0, 'tuas_bbm' => $t['bbm'] ?? 0, 'tuas_logistik' => $t['logistik'] ?? 0,
            'tuas_daur_ulang' => $t['daur_ulang'] ?? 0, 'tuas_limbah' => $t['limbah'] ?? 0,
            'baseline' => $h['baseline'], 'proyeksi' => $h['proyeksi'], 'reduksi_pct' => $h['reduksi_pct'],
            'hemat_t_tahun' => $h['hemat_t_tahun'], 'hemat_idr' => $h['hemat_idr'], 'hemat_eur' => $h['hemat_eur'],
            'rincian' => ['tahap' => $h['tahap'], 'tuas' => $h['tuas']],
        ]);
    }

    /**
     * Daftar periksa tinjauan kritis ISO 14044 §6.
     *
     * @return list<array{kode:string,butir:string,status:string,ket:string,wajib:bool}>
     */
    public static function periksaIso(Studi $s): array
    {
        $items = Inventori::withoutGlobalScopes()->where('studi_id', $s->id)->with(['faktor' => fn ($q) => $q->withoutGlobalScopes()])->get();
        $tahapAda = $items->pluck('tahap')->unique()->all();
        $wajibTahap = match ($s->batas_sistem) {
            'gate_to_gate' => ['olah'],
            'cradle_to_grave', 'cradle_to_cradle' => ['ekstraksi', 'angkut', 'olah', 'distribusi', 'akhir'],
            default => ['ekstraksi', 'angkut', 'olah'],
        };
        $kurang = array_values(array_diff($wajibTahap, $tahapAda));
        $totalAbs = max(1e-9, $items->sum(fn ($i) => abs($i->gwp)));
        $porsiCocok = $items->filter(fn ($i) => $i->faktor?->perlu_cocok)->sum(fn ($i) => abs($i->gwp)) / $totalAbs * 100;
        $nol = $items->filter(fn ($i) => (float) $i->jumlah <= 0)->count();
        $skenario = Skenario::withoutGlobalScopes()->where('studi_id', $s->id)->count();

        $b = fn ($kode, $butir, $ok, $ket, $wajib = true, $warn = false) =>
            ['kode' => $kode, 'butir' => $butir, 'status' => $ok ? 'lulus' : ($warn ? 'peringatan' : 'gagal'), 'ket' => $ket, 'wajib' => $wajib];

        return [
            $b('ISO-1', 'Tujuan, unit fungsional, batas sistem, metode & alokasi tertulis',
                filled($s->tujuan) && $s->fu_nilai > 0 && filled($s->fu_label), filled($s->tujuan) ? 'Lengkap' : 'Tujuan studi belum ditulis'),
            $b('ISO-2', 'Tahap wajib menurut batas sistem terisi', !$kurang,
                $kurang ? 'Belum ada: '.implode(', ', array_map(fn ($t) => Katalog::TAHAP[$t]['pendek'], $kurang)) : count($tahapAda).' tahap terisi'),
            $b('ISO-3', 'Tidak ada baris berjumlah nol / tanpa faktor', $nol === 0 && $items->count() > 0,
                $items->count() ? ($nol ? "{$nol} baris berjumlah nol" : $items->count().' baris inventori') : 'Inventori kosong'),
            $b('ISO-4', 'Kualitas data tertimbang (DQR) ≤ 2,4', $s->dqr !== null && $s->dqr <= 2.4,
                $s->dqr === null ? 'Belum dihitung' : 'DQR '.number_format($s->dqr, 2, ',', '').' — '.Katalog::mutu($s->dqr), true, $s->dqr !== null && $s->dqr <= 3.4),
            $b('ISO-5', 'Faktor belum dicocokkan < 10% GWP', $porsiCocok < 10,
                number_format($porsiCocok, 1, ',', '').'% GWP memakai faktor bawaan yang belum dicocokkan', false, true),
            $b('ISO-6', 'Interpretasi: hotspot & sedikitnya satu skenario perbaikan', $skenario > 0,
                $skenario ? "{$skenario} skenario tersimpan" : 'Simpan satu skenario dekarbonisasi', false, true),
            $b('ISO-7', 'Pembanding tahun dasar ditetapkan', $s->baseline_gwp !== null,
                $s->baseline_gwp !== null ? 'Baseline '.number_format($s->baseline_gwp, 2, ',', '.').' kg CO₂e/FU' : 'Tanpa baseline, tren tidak dapat dinilai', false, true),
        ];
    }

    /** Boleh diajukan/diverifikasi: seluruh butir WAJIB lulus. */
    public static function siap(Studi $s): bool
    {
        return collect(self::periksaIso($s))->where('wajib', true)->every(fn ($b) => $b['status'] === 'lulus');
    }

    /**
     * Tarik data aktivitas dari Energy Performance untuk periode studi,
     * dibagi produksi pada periode yang sama, dikali unit fungsional.
     *
     * Solar alat gali/dozer → ekstraksi, solar hauling → angkutan, solar
     * support & genset + listrik → pengolahan, ANFO/emulsi → ekstraksi.
     * Barisnya bertanda sumber `energi` dan DQR primer (1–2), sehingga
     * dapat dibedakan dari estimasi.
     *
     * @return array{dibuat:int,ton:float,catatan:string}
     */
    public static function imporEnergi(Studi $s, string $kodeListrik = 'EF-EL-KAL'): array
    {
        $c = $s->company_id;
        $dari = Carbon::parse($s->periode_mulai)->startOfDay();
        $sampai = Carbon::parse($s->periode_selesai)->endOfDay();
        $sela = [$dari->toDateString(), $sampai->toDateString()];

        $ton = (float) EnergyProduction::withoutGlobalScopes()->where('company_id', $c)->whereBetween('tanggal', $sela)->sum('ton');
        if ($ton <= 0) return ['dibuat' => 0, 'ton' => 0, 'catatan' => 'Tidak ada data produksi di Energy Performance pada periode studi.'];

        $perFu = $s->fu_nilai * (str_contains(mb_strtolower($s->fu_label), 'ton') ? 1 : ($s->aliran_ref_kg / 1000)) / $ton;

        $alat = EnergyEquipment::withoutGlobalScopes()->where('company_id', $c)->pluck('kategori', 'id');
        $bbm = EnergyFuelLog::withoutGlobalScopes()->whereIn('equipment_id', $alat->keys())->whereBetween('tanggal', $sela)
            ->selectRaw('equipment_id, SUM(liter) as l')->groupBy('equipment_id')->pluck('l', 'equipment_id');
        $perKat = [];
        foreach ($bbm as $id => $l) $perKat[$alat[$id] ?? 'support'] = ($perKat[$alat[$id] ?? 'support'] ?? 0) + (float) $l;

        $listrik = EnergyPowerLog::withoutGlobalScopes()->where('company_id', $c)->whereBetween('tanggal', $sela);
        $kwhGrid = (float) (clone $listrik)->whereIn('sumber', ['pln', 'iuptls'])->sum('kwh');
        $kwhPlts = (float) (clone $listrik)->where('sumber', 'plts')->sum('kwh');
        $genset = (float) (clone $listrik)->sum('liter_genset');
        $lain = EnergyOtherLog::withoutGlobalScopes()->where('company_id', $c)->whereBetween('tanggal', $sela)
            ->selectRaw('jenis, SUM(jumlah) as j')->groupBy('jenis')->pluck('j', 'jenis');

        $f = fn (string $kode) => Faktor::withoutGlobalScopes()->whereNull('company_id')->where('kode', $kode)->first();
        $baris = [
            ['ekstraksi', 'Solar excavator & dozer (Energy Performance)', 'EF-SOLAR-B40', ($perKat['excavator'] ?? 0) + ($perKat['dozer'] ?? 0), 'L'],
            ['angkut',    'Solar dump truck hauling (Energy Performance)', 'EF-SOLAR-B40', $perKat['hauling'] ?? 0, 'L'],
            ['olah',      'Solar alat pendukung & genset (Energy Performance)', 'EF-SOLAR-B40', ($perKat['support'] ?? 0) + $genset, 'L'],
            ['olah',      'Listrik jaringan PLN / IUPTLS (Energy Performance)', $kodeListrik, $kwhGrid, 'kWh'],
            ['olah',      'Listrik PLTS (Energy Performance)', 'EF-EL-PLTS', $kwhPlts, 'kWh'],
            ['ekstraksi', 'Bahan peledak ANFO (Energy Performance)', 'EF-ANFO', (float) ($lain['anfo'] ?? 0), 'kg'],
            ['ekstraksi', 'Bahan peledak emulsi (Energy Performance)', 'EF-EMULSI', (float) ($lain['emulsi'] ?? 0), 'kg'],
            ['ekstraksi', 'Bensin kendaraan ringan (Energy Performance)', 'EF-BENSIN', (float) ($lain['bensin'] ?? 0), 'L'],
            ['olah',      'LPG mess & kantin (Energy Performance)', 'EF-LPG', (float) ($lain['lpg'] ?? 0), 'kg'],
        ];

        // Baris impor sebelumnya diganti, bukan ditumpuk.
        Inventori::withoutGlobalScopes()->where('studi_id', $s->id)->where('sumber_data', 'energi')->delete();

        $n = 0;
        foreach ($baris as [$tahap, $nama, $kode, $total, $satuan]) {
            $fk = $f($kode);
            if (!$fk || $total <= 0) continue;
            self::simpanItem($s, ['faktor_id' => $fk->id, 'tahap' => $tahap, 'nama' => $nama, 'jumlah' => round($total * $perFu, 6),
                'satuan' => $satuan, 'sumber_data' => 'energi', 'p_reliabilitas' => 1, 'p_kelengkapan' => 1, 'p_temporal' => 1,
                'p_geografis' => 1, 'p_teknologi' => 2,
                'catatan' => 'Total periode '.number_format($total, 0, ',', '.').' '.$satuan.' ÷ '.number_format($ton, 0, ',', '.').' t produksi']);
            $n++;
        }

        return ['dibuat' => $n, 'ton' => $ton, 'catatan' => "{$n} baris dari Energy Performance, dibagi ".number_format($ton, 0, ',', '.').' t produksi.'];
    }

    /**
     * Intensitas gate-to-gate bulanan dari catatan Energy Performance
     * (solar + listrik ÷ produksi), kg CO₂e per ton — pembanding cepat
     * terhadap hasil studi tanpa menunggu studi baru dibuat.
     *
     * @return list<array{bulan:string,label:string,nilai:?float}>
     */
    public static function trenEnergi(?int $c, string $kodeListrik = 'EF-EL-KAL', int $bulan = 12): array
    {
        $fs = Faktor::withoutGlobalScopes()->whereNull('company_id')->where('kode', 'EF-SOLAR-B40')->value('gwp') ?? 2.21;
        $fl = Faktor::withoutGlobalScopes()->whereNull('company_id')->where('kode', $kodeListrik)->value('gwp') ?? 1.0;
        $akhir = now()->startOfMonth();
        $awal = $akhir->copy()->subMonths($bulan - 1);
        $alat = EnergyEquipment::withoutGlobalScopes()->when($c, fn ($q) => $q->where('company_id', $c))->pluck('id');

        $bln = fn ($q, string $kol) => $q->whereBetween('tanggal', [$awal->toDateString(), $akhir->copy()->endOfMonth()->toDateString()])
            ->get(['tanggal', $kol])->groupBy(fn ($r) => Carbon::parse($r->tanggal)->format('Y-m'))->map->sum($kol);

        $solar = $bln(EnergyFuelLog::withoutGlobalScopes()->whereIn('equipment_id', $alat), 'liter');
        $listrik = $bln(EnergyPowerLog::withoutGlobalScopes()->when($c, fn ($q) => $q->where('company_id', $c))->whereIn('sumber', ['pln', 'iuptls']), 'kwh');
        $genset = $bln(EnergyPowerLog::withoutGlobalScopes()->when($c, fn ($q) => $q->where('company_id', $c)), 'liter_genset');
        $ton = $bln(EnergyProduction::withoutGlobalScopes()->when($c, fn ($q) => $q->where('company_id', $c)), 'ton');

        $out = [];
        for ($m = $awal->copy(); $m->lte($akhir); $m->addMonth()) {
            $k = $m->format('Y-m');
            $t = (float) ($ton[$k] ?? 0);
            $out[] = ['bulan' => $k, 'label' => $m->translatedFormat('M y'),
                'nilai' => $t > 0 ? round((((float) ($solar[$k] ?? 0) + (float) ($genset[$k] ?? 0)) * $fs + (float) ($listrik[$k] ?? 0) * $fl) / $t, 2) : null];
        }

        return $out;
    }
}
