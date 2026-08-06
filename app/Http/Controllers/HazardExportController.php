<?php

namespace App\Http\Controllers;

use App\Models\{Company, HazardReport, Inspection};
use App\Support\{Db, Ekspor, Hazard};
use Illuminate\Http\Request;

class HazardExportController extends Controller
{
    /* ---------- Ekspor Hazard Report ---------- */
    public function hazardCsv(Request $r)
    {
        $data = $this->saringHazard($r)->get();

        $baris = $data->map(fn($h) => [
            $h->kode,
            optional($h->tanggal)->format('Y-m-d'),
            $h->waktu, $h->pelapor_nama, $h->pelapor_nrp, $h->pelapor_jabatan,
            Hazard::golongan($h->pelapor_jabatan), $h->pelapor_departemen, $h->pelapor_perusahaan,
            $h->company?->name ?: $h->terlapor, $h->lokasi, $h->risiko, $h->kategori,
            $h->deskripsi,
            implode(' | ', $h->unsafe_action_list),
            implode(' | ', $h->unsafe_condition_list),
            $h->hirarki, $h->rekomendasi, $h->status,
            $h->catatan_penutupan, optional($h->closed_at)->format('Y-m-d H:i'),
        ]);

        return Ekspor::csv('hazard-report-'.date('Ymd-Hi'), [
            'Kode','Tanggal','Waktu','Pelapor','NRP','Jabatan','Golongan','Departemen','Perusahaan Pelapor',
            'Ditujukan Kepada','Lokasi','Risiko','Kategori','Deskripsi',
            'Bentuk Unsafe Action','Bentuk Unsafe Condition','Hirarki','Rekomendasi','Status',
            'Catatan Penutupan','Ditutup Pada',
        ], $baris);
    }

    /** Halaman siap cetak → simpan sebagai PDF lewat dialog cetak peramban. */
    public function hazardCetak(Request $r)
    {
        $data = $this->saringHazard($r)->get();
        return view('hazard.cetak', ['data' => $data, 'f' => $r->query()]);
    }

    /* ---------- Ekspor Inspeksi ---------- */
    public function inspeksiCsv(Request $r)
    {
        $q = Inspection::with(['company','template','inspectors','items']);
        if ($r->filled('status'))   $q->where('status', $r->status);
        if ($r->filled('template')) $q->where('template_id', $r->template);

        $baris = [];
        foreach ($q->get() as $i) {
            $inspektur = $i->inspectors->map(fn($p) => $p->nama.' ('.$p->peran.')')->implode(', ');
            foreach ($i->items as $it) {
                $baris[] = [
                    $i->kode, optional($i->tanggal)->format('Y-m-d'), $i->template?->nama, $i->judul,
                    $i->company?->name, $i->lokasi, $i->status, $inspektur,
                    $it->kelompok, $it->uraian, $it->acuan, $it->kondisi, $it->risiko,
                    $it->temuan, $it->tindakan,
                    $it->hazard_report_id ? 'Ya' : 'Tidak',
                ];
            }
        }

        return Ekspor::csv('inspeksi-'.date('Ymd-Hi'), [
            'Kode','Tanggal','Jenis','Judul','Perusahaan','Lokasi','Status','Inspektur',
            'Kelompok','Parameter','Acuan','Kondisi','Risiko','Temuan','Tindakan','Naik ke Hazard Report',
        ], $baris);
    }

    public function inspeksiCetak(Request $r)
    {
        $q = Inspection::with(['company','template','inspectors','items']);
        if ($r->filled('status'))   $q->where('status', $r->status);
        if ($r->filled('template')) $q->where('template_id', $r->template);

        return view('inspeksi.cetak', ['data' => $q->latest('tanggal')->get()]);
    }

    /* ---------- Pengingat tindak lanjut ---------- */
    public function pengingat()
    {
        $perusahaan = Company::orderBy('name')->get()->map(function ($c) {
            $terbuka = HazardReport::where('company_id', $c->id)->where('status', '<>', 'Closed')
                        ->orderBy('tanggal')->get();
            return [
                'c' => $c,
                'terbuka' => $terbuka,
                'tinggi'  => $terbuka->where('risiko', 'Tinggi')->count(),
                'lama'    => $terbuka->filter(fn($h) => $h->tanggal && $h->tanggal->diffInDays(now()) > 14)->count(),
            ];
        })->filter(fn($x) => $x['terbuka']->count() > 0)->values();

        return view('hazard.pengingat', compact('perusahaan'));
    }

    private function saringHazard(Request $r)
    {
        return HazardReport::with('company')
            ->when($r->filled('q'), fn($b) => $b->where(fn($w) => $w
                ->where('kode','like','%'.$r->q.'%')
                ->orWhere('deskripsi','like','%'.$r->q.'%')
                ->orWhere('lokasi','like','%'.$r->q.'%')))
            ->when($r->filled('bulan'),      fn($b) => $b->whereRaw(Db::ym('tanggal') . ' = ?', [$r->bulan]))
            ->when($r->filled('kategori'),   fn($b) => $b->where('kategori', $r->kategori))
            ->when($r->filled('risiko'),     fn($b) => $b->where('risiko', $r->risiko))
            ->when($r->filled('status'),     fn($b) => $b->where('status', $r->status))
            ->when($r->filled('perusahaan'), fn($b) => $b->where('company_id', $r->perusahaan))
            ->latest('tanggal');
    }
}
