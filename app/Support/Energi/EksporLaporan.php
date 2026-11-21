<?php

namespace App\Support\Energi;

use App\Models\Energi\{Jejak, Laporan};
use App\Support\Ekspor;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\{Response, StreamedResponse};

/**
 * Ekspor laporan energi/GRK dari SNAPSHOT-nya (FSD §10): XLSX empat lembar
 * — Ringkasan, Data aktivitas, Faktor, Jejak — CSV baris aktivitas untuk
 * unggah portal, dan JSON snapshot utuh beserta hash-nya.
 *
 * Tidak ada yang dihitung ulang di sini; yang diekspor adalah apa yang
 * dibekukan pada laporan itu.
 */
final class EksporLaporan
{
    public static function unduh(Laporan $l, string $format): Response|StreamedResponse
    {
        $s = $l->snapshot ?? [];
        $nama = 'energi-'.strtolower($l->templat).'-'.$l->tahun.'-v'.$l->versi;

        return match ($format) {
            'json' => response()->json(['laporan' => [
                'templat' => $l->templat, 'judul' => $l->judul, 'versi' => $l->versi, 'status' => $l->status,
                'nomor_dokumen' => $l->nomor_dokumen, 'hash_snapshot' => $l->hash_snapshot,
            ], 'snapshot' => $s], 200, ['Content-Disposition' => 'attachment; filename="'.$nama.'.json"'],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            'csv' => Ekspor::csv($nama, ['Scope', 'Sumber', 'Kelompok', 'Energi', 'Jumlah', 'Satuan', 'GJ',
                    'CO2 fosil (t)', 'CO2 biogenik (t)', 'CH4 (t)', 'N2O (t)', 'CO2e (t)', 'CO2e market-based (t)'],
                array_map(fn ($b) => [$b['scope'], $b['label'], $b['kelompok'], $b['energi'], self::a($b['jumlah']),
                    $b['satuan'], self::a($b['gj']), self::a($b['co2_fosil_t']), self::a($b['co2_bio_t']),
                    self::a($b['ch4_t'], 6), self::a($b['n2o_t'], 6), self::a($b['co2e_t']), self::a($b['co2e_mb_t'])],
                    $s['per_sumber'] ?? [])),
            default => self::xlsx($l, $s, $nama),
        };
    }

    private static function xlsx(Laporan $l, array $s, string $nama): StreamedResponse
    {
        $x = new Spreadsheet();

        $r = $s['ringkas'] ?? [];
        $lembar = $x->getActiveSheet()->setTitle('Ringkasan');
        $baris = [
            ['Laporan', $l->judul], ['Nomor dokumen', $l->nomor_dokumen], ['Versi', $l->versi], ['Status', $l->status],
            ['Perusahaan', $s['perusahaan']['nama'] ?? ''], ['Periode', implode(' s.d. ', $s['periode'] ?? [])],
            ['Dasar', $s['dasar'] ?? ''], ['Set GWP', $s['gwp_set'] ?? ''], ['Hash snapshot (SHA-256)', $l->hash_snapshot],
            [],
            ['Energi total (GJ)', $r['gj'] ?? null], ['Energi total (TOE)', $r['toe'] ?? null], ['Energi (SBM)', $s['sbm'] ?? null],
            ['Scope 1 (tCO2e)', $r['s1'] ?? null], ['Scope 2 location-based (tCO2e)', $r['s2_lb'] ?? null],
            ['Scope 2 market-based (tCO2e)', $r['s2_mb'] ?? null], ['Scope 3 (tCO2e)', $r['s3'] ?? null],
            ['Scope 3 kat. 11 — terpisah (tCO2e)', $r['s3_terpisah'] ?? null], ['CO2 biogenik (t) — terpisah', $r['co2_bio'] ?? null],
            ['Biaya energi (Rp)', $r['rupiah'] ?? null], ['Produksi (ton)', $s['produksi']['ton'] ?? null],
            ['Intensitas energi (GJ/ton)', $s['intensitas']['gj_ton'] ?? null], ['Intensitas emisi (tCO2e/ton)', $s['intensitas']['tco2e_ton'] ?? null],
            ['Ketidakpastian gabungan (%)', $r['ketidakpastian_persen'] ?? null], ['Data terverifikasi (%)', $r['persen_terverifikasi'] ?? null],
        ];
        $lembar->fromArray($baris, null, 'A1');
        $lembar->getColumnDimension('A')->setWidth(38);
        $lembar->getColumnDimension('B')->setWidth(48);

        $akt = $x->createSheet()->setTitle('Data aktivitas');
        $akt->fromArray(['Scope', 'Sumber', 'Kelompok', 'Energi', 'Kategori IPCC', 'Jumlah', 'Satuan', 'GJ', 'CO2 fosil (t)',
                         'CO2 biogenik (t)', 'CH4 (t)', 'N2O (t)', 'CO2e (t)', 'CO2e MB (t)', 'Rp', 'Faktor kurang'], null, 'A1');
        $akt->fromArray(array_map(fn ($b) => [$b['scope'], $b['label'], $b['kelompok'], $b['energi'], $b['kategori_ipcc'],
            $b['jumlah'], $b['satuan'], $b['gj'], $b['co2_fosil_t'], $b['co2_bio_t'], $b['ch4_t'], $b['n2o_t'], $b['co2e_t'],
            $b['co2e_mb_t'], $b['rupiah'], implode(', ', $b['kurang'] ?? [])], $s['per_sumber'] ?? []), null, 'A2');

        $fak = $x->createSheet()->setTitle('Faktor');
        $fak->fromArray(['ID', 'Kode energi', 'Parameter', 'Kategori IPCC', 'Wilayah', 'Nilai', 'Satuan', 'Berlaku mulai',
                         'Berlaku sampai', 'Tier', 'Sumber dokumen', 'Rujukan', 'Perlu dicocokkan'], null, 'A1');
        $fak->fromArray(array_map(fn ($f) => [$f['id'], $f['kode_energi'], $f['parameter'], $f['kategori_ipcc'], $f['wilayah'],
            $f['nilai'], $f['satuan'], $f['berlaku_mulai'], $f['berlaku_sampai'], $f['tier'], $f['sumber_dokumen'],
            $f['sumber_rujukan'], $f['perlu_cocok'] ? 'ya' : 'tidak'], $s['faktor'] ?? []), null, 'A2');

        $jej = $x->createSheet()->setTitle('Jejak');
        $jej->fromArray(['Waktu', 'Aksi', 'Subjek', 'ID', 'Pengguna', 'Perubahan'], null, 'A1');
        $jejak = Jejak::with('user:id,name')->where('company_id', $l->company_id)
            ->whereBetween('created_at', [$l->periode_mulai->copy()->startOfDay(), now()])
            ->orderByDesc('id')->limit(2000)->get();
        $jej->fromArray($jejak->map(fn ($j) => [$j->created_at?->toDateTimeString(), $j->aksi, class_basename($j->subjek_type),
            $j->subjek_id, $j->user?->name, json_encode($j->perubahan, JSON_UNESCAPED_UNICODE)])->all(), null, 'A2');

        $x->setActiveSheetIndex(0);

        return response()->streamDownload(function () use ($x) {
            (new Xlsx($x))->save('php://output');
        }, $nama.'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    private static function a($v, int $d = 3): string
    {
        return $v === null ? '' : number_format((float) $v, $d, ',', '');
    }
}
