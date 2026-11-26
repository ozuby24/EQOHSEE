<?php

namespace App\Support\Meranti;

use Illuminate\Support\Collection;

/**
 * Perhitungan MERANTI — dipindahkan apa adanya dari Code.gs supaya angka
 * di EQOHSEE sama dengan yang selama ini dibaca dari aplikasi lamanya.
 *
 * - Matrik 17: skor = 60% penatagunaan lahan + 20% revegetasi + 20%
 *   penyelesaian akhir; > 80 Baik, 60–80 Sedang, < 60 Buruk.
 * - Aktual Pencapaian: Σ luas realisasi Progres Mingguan ÷ Σ luas Target
 *   Blok, per blok dan per pekerjaan P0/P1/P2.
 * - PKAR: luas reklamasi ÷ luas bukaan pada Rencana Reklamasi.
 */
final class Hitung
{
    public const AMBANG_BAIK = 80;
    public const AMBANG_SEDANG = 60;

    /** @return array{skor_total: float, kategori: string} */
    public static function matrik17(mixed $penatagunaan, mixed $revegetasi, mixed $penyelesaian): array
    {
        $skor = round(0.6 * self::angka($penatagunaan) + 0.2 * self::angka($revegetasi) + 0.2 * self::angka($penyelesaian), 2);

        return ['skor_total' => $skor, 'kategori' => self::kategori($skor)];
    }

    public static function kategori(?float $skor): string
    {
        if ($skor === null) return '—';

        return $skor > self::AMBANG_BAIK ? 'Baik' : ($skor >= self::AMBANG_SEDANG ? 'Sedang' : 'Buruk');
    }

    /**
     * Angka dari isian lembar atau formulir.
     *
     * Lembar Google Sheets berlokal Indonesia mengirim "48,5" dan "6,160809";
     * pemisah ribuan "1.250.000" juga mungkin. Satu koma tanpa titik dibaca
     * sebagai desimal; titik berulang dibaca sebagai pemisah ribuan.
     */
    public static function angka(mixed $v): float
    {
        return self::angkaAtauNull($v) ?? 0.0;
    }

    public static function angkaAtauNull(mixed $v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_int($v) || is_float($v)) return (float) $v;

        $s = str_replace([' ', "\u{00A0}", 'Rp', 'rp'], '', trim((string) $v));
        if ($s === '' || $s === '-') return null;

        if (str_contains($s, ',') && str_contains($s, '.')) {
            // 1.250.000,50 → titik ribuan, koma desimal; 1,250,000.50 → kebalikannya.
            $s = strrpos($s, ',') > strrpos($s, '.') ? str_replace(['.', ','], ['', '.'], $s) : str_replace(',', '', $s);
        } elseif (str_contains($s, ',')) {
            $s = substr_count($s, ',') === 1 ? str_replace(',', '.', $s) : str_replace(',', '', $s);
        } elseif (substr_count($s, '.') > 1) {
            $s = str_replace('.', '', $s);
        }

        return is_numeric($s) ? (float) $s : null;
    }

    public static function indeksBulan(?string $bulan, mixed $tanggal = null): int
    {
        $ix = array_search(trim((string) $bulan), Katalog::BULAN, true);
        if ($ix !== false) return (int) $ix;

        $t = $tanggal instanceof \DateTimeInterface ? $tanggal->format('Y-m-d') : (string) $tanggal;
        $m = (int) substr($t, 5, 2) - 1;

        return $m >= 0 && $m < 12 ? $m : -1;
    }

    public static function indeksMinggu(?string $minggu): int
    {
        return (int) preg_replace('/\D+/', '', (string) $minggu) ?: 0;
    }

    /**
     * Urutan kronologis Progres Mingguan: tahun → bulan → minggu (terbaru
     * di atas), seperti `sortDAS` di MERANTI. Nama bulan tidak dapat
     * diurutkan sebagai teks, jadi diurutkan di sini.
     */
    public static function urutProgres(Collection $baris): Collection
    {
        return $baris->sort(function ($a, $b) {
            return [(int) $b->tahun, self::indeksBulan($b->bulan, $b->tanggal), self::indeksMinggu($b->minggu), $b->id]
                <=> [(int) $a->tahun, self::indeksBulan($a->bulan, $a->tanggal), self::indeksMinggu($a->minggu), $a->id];
        })->values();
    }

    /** Urutan alami nama blok: Blok 1, Blok 2, Blok 10. */
    public static function urutBlok(string $a, string $b): int
    {
        $na = (int) (preg_match('/\d+/', $a, $m) ? $m[0] : 0);
        $nb = (int) (preg_match('/\d+/', $b, $m) ? $m[0] : 0);

        return $na !== $nb ? $na <=> $nb : strnatcasecmp($a, $b);
    }

    /**
     * Aktual Pencapaian — persen capaian P0/P1/P2 per blok.
     *
     * Target tanpa tahun berlaku untuk tahun mana pun (sama dengan
     * MERANTI). Persen di atas 150 dipotong pada grafik saja; tabel
     * rinciannya tetap menyebut angka sebenarnya.
     *
     * @param Collection<int, \App\Models\Meranti\Progres> $progres
     * @param Collection<int, \App\Models\Meranti\TargetBlok> $target
     */
    public static function capaian(Collection $progres, Collection $target, ?string $tahun): array
    {
        $tahunList = $progres->pluck('tahun')->merge($target->pluck('tahun'))->filter(fn ($t) => $t !== null && $t !== '')
            ->map(fn ($t) => (string) $t)->unique()->sort()->values()->all();

        $pakaiTahun = $tahun !== null && $tahun !== '' && mb_strtolower($tahun) !== 'semua';
        if ($pakaiTahun) {
            $progres = $progres->filter(fn ($r) => (string) $r->tahun === $tahun);
            $target = $target->filter(fn ($r) => $r->tahun === null || (string) $r->tahun === $tahun);
        }

        $P = Katalog::PEKERJAAN;
        $peta = [];
        $slot = function (string $b) use (&$peta, $P) {
            $peta[$b] ??= array_fill_keys($P, ['real' => 0.0, 'target' => 0.0]);
        };

        foreach ($progres as $r) {
            $b = trim((string) $r->nama_blok) ?: '-';
            $k = mb_strtoupper(trim((string) $r->pekerjaan));
            if (!in_array($k, $P, true)) continue;
            $slot($b);
            $peta[$b][$k]['real'] += (float) $r->luas_realisasi;
        }
        foreach ($target as $r) {
            $b = trim((string) $r->nama_blok) ?: '-';
            $k = mb_strtoupper(trim((string) $r->pekerjaan));
            if (!in_array($k, $P, true)) continue;
            $slot($b);
            $peta[$b][$k]['target'] += (float) $r->luas_target;
        }

        $label = array_keys($peta);
        usort($label, [self::class, 'urutBlok']);

        $pct = fn (array $o) => $o['target'] > 0 ? round($o['real'] / $o['target'] * 100, 1) : null;

        $deret = [];
        foreach ($P as $k) {
            $deret[$k] = array_map(fn ($b) => ($v = $pct($peta[$b][$k])) === null ? null : min(150, $v), $label);
        }

        $rinci = [];
        $sumReal = 0.0;
        $sumTarget = 0.0;
        foreach ($label as $b) {
            $d = ['blok' => $b];
            foreach ($P as $k) {
                $o = $peta[$b][$k];
                $d[$k] = ['real' => round($o['real'], 2), 'target' => round($o['target'], 2), 'pct' => $pct($o)];
                $sumReal += $o['real'];
                $sumTarget += $o['target'];
            }
            $rinci[] = $d;
        }

        $perPekerjaan = [];
        foreach ($P as $k) {
            $real = array_sum(array_map(fn ($b) => $peta[$b][$k]['real'], $label));
            $tgt = array_sum(array_map(fn ($b) => $peta[$b][$k]['target'], $label));
            $perPekerjaan[$k] = ['real' => round($real, 2), 'target' => round($tgt, 2), 'pct' => $tgt > 0 ? round($real / $tgt * 100, 1) : null];
        }

        return [
            'label' => $label, 'deret' => $deret, 'rinci' => $rinci, 'perPekerjaan' => $perPekerjaan,
            'total' => [
                'real' => round($sumReal, 2), 'target' => round($sumTarget, 2),
                'pct' => $sumTarget > 0 ? round($sumReal / $sumTarget * 100, 1) : 0, 'blok' => count($label),
            ],
            'tahunList' => $tahunList,
            'tahun' => $pakaiTahun ? $tahun : 'Semua',
        ];
    }

    /**
     * Ringkasan dasbor — kartu dan grafik "Dashboard Analitik" MERANTI.
     *
     * @param array<string, Collection> $d stok_bibit, stok_material, progres, target, vendor, rencana, penilaian
     */
    public static function dasbor(array $d, ?string $tahun): array
    {
        $progres = $d['progres'];
        $semuaTahun = $progres->pluck('tahun')->filter()->map(fn ($t) => (int) $t)->unique()->sortDesc()->values()->all();
        $pakaiTahun = $tahun !== null && $tahun !== '' && $tahun !== 'semua';
        $prog = $pakaiTahun ? $progres->filter(fn ($r) => (string) $r->tahun === (string) $tahun) : $progres;
        $target = $pakaiTahun ? $d['target']->filter(fn ($r) => $r->tahun === null || (string) $r->tahun === (string) $tahun) : $d['target'];

        $totalRealisasi = (float) $prog->sum(fn ($r) => (float) $r->luas_realisasi);
        $totalTarget = (float) $target->sum(fn ($r) => (float) $r->luas_target);

        $perBulan = array_fill(0, 12, 0.0);
        foreach ($prog as $r) {
            $m = self::indeksBulan($r->bulan, $r->tanggal);
            if ($m > -1) $perBulan[$m] += (float) $r->luas_realisasi;
        }

        // Bulan yang belum dilalui pada tahun berjalan tidak digambar sebagai nol —
        // garis tren yang jatuh ke 0 di Desember terbaca sebagai pekerjaan berhenti.
        if ($pakaiTahun && (int) $tahun === (int) now()->year) {
            foreach ($perBulan as $m => $v) {
                if ($m >= (int) now()->month && $v == 0) $perBulan[$m] = null;
            }
        }

        $pkj = array_fill_keys(Katalog::PEKERJAAN, 0.0);
        foreach ($prog as $r) {
            $k = mb_strtoupper(trim((string) $r->pekerjaan));
            if (isset($pkj[$k])) $pkj[$k] += (float) $r->luas_realisasi;
        }

        $blok = [];
        foreach ($prog as $r) {
            $b = trim((string) $r->nama_blok) ?: '-';
            $blok[$b] = ($blok[$b] ?? 0) + (float) $r->luas_realisasi;
        }
        arsort($blok);

        $nilai = $d['penilaian'];
        $skor = $nilai->count() ? round($nilai->avg(fn ($r) => (float) $r->skor_total), 1) : null;
        $rencana = $d['rencana']->sortBy('tahun')->values();
        $luasBukaan = (float) $rencana->sum(fn ($r) => (float) $r->luas_bukaan_ha);
        $luasReklamasi = (float) $rencana->sum(fn ($r) => (float) $r->luas_reklamasi_ha);

        $bibit = $d['stok_bibit'];
        $perGolongan = [];
        foreach ($bibit as $r) {
            $g = $r->golongan ?: 'Lainnya';
            $perGolongan[$g] = ($perGolongan[$g] ?? 0) + (int) $r->stok;
        }

        return [
            'tahun' => $pakaiTahun ? (string) $tahun : 'semua',
            'tahunList' => $semuaTahun,
            'kartu' => [
                'jenisBibit' => $bibit->count(),
                'totalStok' => (int) $bibit->sum(fn ($r) => (int) $r->stok),
                'jenisMaterial' => $d['stok_material']->count(),
                'totalRealisasi' => round($totalRealisasi, 2),
                'totalTarget' => round($totalTarget, 2),
                'persenTarget' => $totalTarget > 0 ? round($totalRealisasi / $totalTarget * 100, 1) : null,
                'recordProgres' => $prog->count(),
                'totalVendor' => $d['vendor']->count(),
                'skor' => $skor,
                'kategoriSkor' => self::kategori($skor),
                'penilaian' => $nilai->count(),
                'luasReklamasi' => round($luasReklamasi, 2),
                'luasBukaan' => round($luasBukaan, 2),
                'pkar' => $luasBukaan > 0 ? round($luasReklamasi / $luasBukaan * 100, 1) : null,
            ],
            'perBulan' => array_map(fn ($v) => $v === null ? null : round($v, 2), $perBulan),
            'pekerjaan' => array_map(fn ($v) => round($v, 2), $pkj),
            'blok' => collect($blok)->take(6)->map(fn ($v, $k) => ['label' => (string) $k, 'nilai' => round($v, 2)])->values()->all(),
            'luas' => [
                'label' => $rencana->map(fn ($r) => (string) $r->tahun)->all(),
                'bukaan' => $rencana->map(fn ($r) => (float) $r->luas_bukaan_ha)->all(),
                'reklamasi' => $rencana->map(fn ($r) => (float) $r->luas_reklamasi_ha)->all(),
            ],
            'golongan' => collect($perGolongan)->map(fn ($v, $k) => ['label' => (string) $k, 'nilai' => $v])->values()->all(),
        ];
    }
}
