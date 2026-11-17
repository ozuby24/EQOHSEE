<?php

namespace App\Http\Controllers;

use App\Models\{SmkpAudit, SmkpBukti, SmkpFinding};
use App\Support\{SmkpDasbor, Waktu};
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Dasbor Audit SMKP — desain v2 mengikuti dasbor DBEST: KPI per babak,
 * rata-rata penerapan beserta selisih terhadap tahun pembanding dan tren
 * empat tahun, sebaran tingkat, radar tujuh elemen, temuan per elemen &
 * kategori, analisis temuan & tindak lanjut, agenda 30 hari, dan ekspor
 * Excel.
 *
 * Seluruh audit dibaca lewat SmkpAudit, yang terikat Scopes\JangkauanAudit:
 * dasbor tidak pernah menghitung audit perusahaan di luar jangkauan
 * penggunanya, termasuk pada tahun pembanding dan tren lintas tahun.
 */
class SmkpDasborController extends Controller
{
    public function index(Request $request)
    {
        $tahun   = $this->tahun($request);
        $banding = $this->tahunBanding($request, $tahun);

        $tahunAda = SmkpAudit::query()->tanpaGrup()->select('tahun')->distinct()->pluck('tahun')
            ->push((int) Waktu::kini()->year)->push($tahun)
            ->map(fn ($t) => (int) $t)->unique()->sortDesc()->values()->all();

        return Inertia::render('Smkp/Dasbor', [
            'dasbor'   => $this->data($tahun, $banding),
            'tahunAda' => $tahunAda,
            'pilihanBanding' => array_values(array_filter($tahunAda, fn ($t) => $t !== $tahun)),
            'bisaSunting' => $request->user()->bisaAudit(),
        ]);
    }

    /**
     * Ekspor dasbor ke Excel (.xlsx): ringkasan, nilai per perusahaan dan
     * perubahannya terhadap tahun pembanding, capaian 7 elemen, dan temuan.
     */
    public function ekspor(Request $request)
    {
        $tahun   = $this->tahun($request);
        $banding = $this->tahunBanding($request, $tahun);
        $audits  = $this->audit([$tahun]);
        $temuan  = $this->temuan($audits);
        $d       = $this->data($tahun, $banding, $audits, $temuan);

        $buku = $this->buku($d, $temuan, $audits);
        $nama = 'dasbor-smkp-'.$tahun.($banding ? '-vs-'.$banding : '').'.xlsx';

        return response()->streamDownload(function () use ($buku) {
            (new Xlsx($buku))->save('php://output');
        }, $nama, [
            'Content-Type'           => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control'          => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function tahun(Request $request): int
    {
        $t = (string) $request->query('tahun', '');

        return ctype_digit($t) && (int) $t >= 2000 && (int) $t <= 2100 ? (int) $t : (int) Waktu::kini()->year;
    }

    /** Tahun pembanding: pilihan pengguna, "tidak", atau tahun audit terakhir sebelum periode. */
    private function tahunBanding(Request $request, int $tahun): ?int
    {
        $b = (string) $request->query('banding', '');
        if ($b === 'tidak') return null;
        if (ctype_digit($b) && (int) $b >= 2000 && (int) $b <= 2100 && (int) $b !== $tahun) return (int) $b;

        $lalu = SmkpAudit::query()->tanpaGrup()->where('tahun', '<', $tahun)->max('tahun');

        return $lalu ? (int) $lalu : null;
    }

    /** @return Collection<int, SmkpAudit> */
    private function audit(array $tahun): Collection
    {
        // Audit perusahaan grup tidak ikut dasbor, statistik, maupun pembanding (port DBEST #21).
        return SmkpAudit::with('company:id,name,kategori,parent_id')->tanpaGrup()
            ->whereIn('tahun', $tahun)->orderBy('id')->get();
    }

    /** @return Collection<int, SmkpFinding> */
    private function temuan(Collection $audits): Collection
    {
        return SmkpFinding::whereIn('audit_id', $audits->pluck('id')->all() ?: [0])->get();
    }

    /**
     * Data dasbor (SmkpDasbor::susun): tren nilai 4 tahun, audit tahun
     * pembanding, dan temuan 5 tahun terakhir untuk tren tindak lanjut tahunan.
     */
    private function data(int $tahun, ?int $banding, ?Collection $audits = null, ?Collection $temuan = null): array
    {
        $audits ??= $this->audit([$tahun]);
        $temuan ??= $this->temuan($audits);

        $tahunTren   = range($tahun - 3, $tahun);
        $tahunLintas = range($tahun - 4, $tahun - 1);
        $lain = $this->audit(array_values(array_unique(array_merge($tahunLintas, $banding ? [$banding] : []))))
            ->groupBy(fn ($a) => (int) $a->tahun);

        $auditLintas = $audits;
        foreach ($tahunLintas as $t) $auditLintas = $auditLintas->merge($lain->get($t) ?: collect());
        $idLalu = $auditLintas->pluck('id')->diff($audits->pluck('id'))->values()->all();
        $temuanLintas = $temuan->merge($idLalu ? SmkpFinding::whereIn('audit_id', $idLalu)->get(['id', 'audit_id', 'status']) : collect());

        $tren = [];
        foreach ($tahunTren as $t) {
            $kol = $t === $tahun ? $audits : ($lain->get($t) ?: collect());
            $skor = [];
            foreach ($kol as $a) {
                $r = $a->rekap();
                if ($r['dinilai'] > 0) $skor[] = round((float) $r['skor'], 1);
            }
            $tren[$t] = $skor ? round(array_sum($skor) / count($skor), 1) : null;
        }

        $bukti = SmkpBukti::whereIn('audit_id', $audits->pluck('id')->all() ?: [0])->get(['id', 'audit_id', 'status']);

        return SmkpDasbor::susun($tahun, $banding, $audits, $banding ? ($lain->get($banding) ?: collect()) : collect(),
            $temuan, $bukti, $tren, Waktu::kini()->toDateString(), ['audit' => $auditLintas, 'temuan' => $temuanLintas]);
    }

    private function buku(array $d, Collection $temuan, Collection $audits): Spreadsheet
    {
        $buku = new Spreadsheet();
        $buku->getProperties()->setCreator('EQOHSEE')->setTitle('Dasbor Audit SMKP '.$d['tahun']);
        $f = fn ($v) => $v === null ? '' : number_format((float) $v, 1, ',', '.');
        $k = $d['kpi'];

        // 1. Ringkasan
        $ws = $buku->getActiveSheet()->setTitle('Ringkasan');
        $baris = [
            ['Dasbor Audit SMKP', ''],
            ['Periode', (string) $d['tahun']],
            ['Pembanding', $d['banding'] ? (string) $d['banding'] : 'Tanpa pembanding'],
            ['Jumlah audit', (string) $k['audit']],
            ['Tahap I', (string) $k['langkah'][1]],
            ['Rencana Audit', (string) $k['langkah'][2]],
            ['Audit lapangan', (string) $k['langkah'][3]],
            ['Pelaporan', (string) $k['langkah'][4]],
            ['Audit dinilai', (string) $k['dinilai']],
            ['Rata-rata nilai akhir (%)', $f($k['rata'])],
            ['Rata-rata pembanding (%)', $f($k['rataBanding'])],
            ['Baik (≥ 85%)', (string) $d['sebar']['Baik']],
            ['Perlu Perbaikan (60–85%)', (string) $d['sebar']['Perlu Perbaikan']],
            ['Perlu Perhatian Serius (< 60%)', (string) $d['sebar']['Perlu Perhatian Serius']],
            ['Belum dinilai', (string) $d['sebar']['Belum dinilai']],
            ['Temuan', (string) $k['temuan']['total']],
            ['Temuan terbuka', (string) $k['temuan']['buka']],
            ['Lewat target', (string) $k['temuan']['telat']],
            ['Tindak lanjut ditutup', $k['temuan']['persenTutup'].'%'],
            ['Bukti diterima', $k['bukti'][SmkpBukti::DITERIMA].' dari '.$k['bukti']['total'].' berkas'],
            ['Bukti menunggu verifikasi', (string) $k['bukti'][SmkpBukti::MENUNGGU]],
            ['', ''],
            ['Sorotan', ''],
        ];
        foreach ($d['sorotan'] as $s) {
            $baris[] = ['', implode('', array_map(fn ($r) => $r['t'] ?? $r['b'] ?? '', $s['ruas']))];
        }
        $ws->fromArray($baris, null, 'A1');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $ws->getColumnDimension('A')->setWidth(34);
        $ws->getColumnDimension('B')->setWidth(90);

        // 2. Nilai per perusahaan
        $ws = $buku->createSheet()->setTitle('Nilai Perusahaan');
        $b = $d['banding'] ?: 'pembanding';
        $isi = [['No', 'Perusahaan', 'Kategori', 'Babak', 'Nilai akhir (%)', 'Tingkat penerapan', 'Poin', 'Poin maksimal',
                 'Nilai '.$b.' (%)', 'Perubahan (p.p)', 'Temuan', 'Terbuka', 'Lewat target', 'Bukti diterima']];
        foreach ($d['perusahaan'] as $i => $p) {
            $isi[] = [$i + 1, $p['nama'], $p['kat'], $p['babak'], $f($p['skor']), $p['tingkat'], $f($p['poin']), $p['maks'],
                      $f($p['lalu']), $p['skor'] !== null && $p['lalu'] !== null ? $f($p['skor'] - $p['lalu']) : '',
                      $p['temuan'], $p['buka'], $p['telat'], $p['buktiTerima'].'/'.$p['bukti']];
        }
        $ws->fromArray($isi, null, 'A1');
        $ws->getStyle('A1:N1')->getFont()->setBold(true);
        foreach (range('A', 'N') as $c) $ws->getColumnDimension($c)->setAutoSize(true);

        // 3. Capaian elemen
        $ws = $buku->createSheet()->setTitle('Capaian Elemen');
        $kepala = ['Perusahaan'];
        foreach ($d['elemen'] as $e) $kepala[] = $e['k'].'. '.$e['n'].' ('.$e['bobot'].'%)';
        $isi = [$kepala];
        foreach ($d['perusahaan'] as $p) $isi[] = array_merge([$p['nama']], array_map($f, $p['el']));
        $isi[] = array_merge(['Rata-rata'], array_map($f, $d['elRata']));
        if ($d['banding']) $isi[] = array_merge(['Rata-rata '.$d['banding']], array_map($f, $d['elRataBanding']));
        $ws->fromArray($isi, null, 'A1');
        $ws->getStyle('A1:H1')->getFont()->setBold(true);
        foreach (range('A', 'H') as $c) $ws->getColumnDimension($c)->setAutoSize(true);

        // 4. Temuan
        $ws = $buku->createSheet()->setTitle('Temuan');
        $nama = $audits->mapWithKeys(fn ($a) => [$a->id => $a->company?->name ?? $a->namaTampil()]);
        $isi = [['No', 'Perusahaan', 'Nomor', 'Kriteria', 'Kategori', 'Status', 'Target selesai', 'Tanggal selesai', 'Lewat target']];
        foreach ($temuan->values() as $i => $t) {
            $isi[] = [$i + 1, $nama[$t->audit_id] ?? '', (string) $t->nomor, (string) $t->kode_kriteria, $t->labelKategori(),
                      (string) $t->status, $t->target_selesai?->format('d/m/Y') ?? '', $t->tanggal_selesai?->format('d/m/Y') ?? '',
                      $t->terlambat() ? 'Ya' : ''];
        }
        $ws->fromArray($isi, null, 'A1');
        $ws->getStyle('A1:I1')->getFont()->setBold(true);
        foreach (range('A', 'I') as $c) $ws->getColumnDimension($c)->setAutoSize(true);

        $buku->setActiveSheetIndex(0);

        return $buku;
    }
}
