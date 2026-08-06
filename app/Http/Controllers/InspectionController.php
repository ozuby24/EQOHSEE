<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Company, HazardReport, Inspection, InspectionInspector,
    InspectionItem, InspectionTemplate, User};
use App\Support\{Db, Hazard};
use Illuminate\Http\Request;

class InspectionController extends Controller
{
    public function index(Request $request)
    {
        $status   = $request->get('status');
        $template = $request->get('template');

        $inspections = Inspection::with(['company','template','inspectors'])->withCount('items')
            ->when($status,   fn($b) => $b->where('status', $status))
            ->when($template, fn($b) => $b->where('template_id', $template))
            ->latest('tanggal')->paginate(15)->withQueryString();

        return view('inspeksi.index', [
            'inspections' => $inspections, 'status' => $status, 'template' => $template,
            'templates'   => InspectionTemplate::orderBy('nama')->get(),
        ]);
    }

    public function create(Request $request)
    {
        return view('inspeksi.form', [
            'inspection' => new Inspection(),
            'companies'  => Company::orderBy('name')->get(),
            'templates'  => InspectionTemplate::where('is_active', true)->withCount('items')->orderBy('nama')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $d = $this->v($request);
        $d['kode']    = Inspection::kodeBaru();
        $d['user_id'] = auth()->id();

        $i = Inspection::create($d);

        // Salin parameter dari template terpilih
        if ($i->template_id) {
            $tpl = InspectionTemplate::with('items')->find($i->template_id);
            foreach ($tpl?->items ?? [] as $it) {
                $i->items()->create([
                    'template_item_id' => $it->id, 'kelompok' => $it->kelompok,
                    'uraian' => $it->uraian, 'acuan' => $it->acuan,
                    'risiko' => $it->risiko_default, 'order_index' => $it->order_index,
                ]);
            }
        }

        // Pembuat otomatis jadi inspektur ketua
        $me = auth()->user();
        $i->inspectors()->create([
            'user_id' => $me->id, 'nama' => $me->name,
            'jabatan' => $me->position, 'peran' => 'Ketua',
        ]);

        ActivityLog::write('Buat inspeksi', $i->kode.' — '.$i->judul, 'hazrep');
        return redirect()->route('inspeksi.show', $i)->with('ok','Inspeksi '.$i->kode.' dibuat.');
    }

    public function show(Inspection $inspeksi)
    {
        $inspeksi->load(['items.hazardReport','company','user','template','inspectors']);
        return view('inspeksi.show', [
            'i'        => $inspeksi,
            'kandidat' => User::orderBy('name')->get(),
        ]);
    }

    public function edit(Inspection $inspeksi)
    {
        return view('inspeksi.form', [
            'inspection' => $inspeksi,
            'companies'  => Company::orderBy('name')->get(),
            'templates'  => InspectionTemplate::where('is_active', true)->withCount('items')->orderBy('nama')->get(),
        ]);
    }

    public function update(Request $request, Inspection $inspeksi)
    {
        $inspeksi->update($this->v($request));
        return redirect()->route('inspeksi.show', $inspeksi)->with('ok','Inspeksi diperbarui.');
    }

    public function destroy(Inspection $inspeksi)
    {
        $kode = $inspeksi->kode;
        $inspeksi->delete();
        ActivityLog::write('Hapus inspeksi', $kode, 'hazrep');
        return redirect()->route('inspeksi.index')->with('ok','Inspeksi dihapus.');
    }

    /* ---------- Inspektur ---------- */
    public function addInspector(Request $r, Inspection $inspeksi)
    {
        $d = $r->validate([
            'user_id' => ['nullable','exists:users,id'],
            'nama'    => ['required_without:user_id','nullable','string','max:150'],
            'jabatan' => ['nullable','string','max:100'],
            'peran'   => ['nullable','in:Ketua,Anggota'],
        ]);

        if (!empty($d['user_id'])) {
            $u = User::find($d['user_id']);
            $d['nama']    = $u->name;
            $d['jabatan'] = $d['jabatan'] ?: $u->position;
        }
        $d['peran'] = $d['peran'] ?? 'Anggota';

        $inspeksi->inspectors()->create($d);
        return back()->with('ok','Inspektur ditambahkan.');
    }

    public function removeInspector(InspectionInspector $inspector)
    {
        $inspector->delete();
        return back()->with('ok','Inspektur dihapus.');
    }

    /* ---------- Item pemeriksaan ---------- */
    public function saveItems(Request $request, Inspection $inspeksi)
    {
        foreach ((array) $request->input('item', []) as $id => $row) {
            $item = $inspeksi->items()->find($id);
            if (!$item) continue;
            $item->update([
                'kondisi'  => in_array($row['kondisi'] ?? null, Hazard::KONDISI, true) ? $row['kondisi'] : null,
                'risiko'   => in_array($row['risiko'] ?? null, Hazard::RISIKO, true) ? $row['risiko'] : null,
                'temuan'   => $row['temuan']   ?? null,
                'tindakan' => $row['tindakan'] ?? null,
            ]);
        }
        if ($request->filled('status')) $inspeksi->update(['status' => $request->input('status')]);

        return back()->with('ok','Hasil pemeriksaan tersimpan.');
    }

    public function storeItem(Request $request, Inspection $inspeksi)
    {
        $d = $request->validate([
            'uraian'   => ['required','string','max:300'],
            'kelompok' => ['nullable','string','max:100'],
            'kondisi'  => ['nullable','in:Sesuai,Tidak Sesuai,N/A'],
            'risiko'   => ['nullable','in:Rendah,Sedang,Tinggi'],
            'temuan'   => ['nullable','string','max:2000'],
            'tindakan' => ['nullable','string','max:2000'],
        ]);
        $d['order_index'] = (int) $inspeksi->items()->max('order_index') + 1;

        $foto = [];
        foreach ((array) $request->file('foto') as $f) {
            if ($f && $f->isValid()) $foto[] = $f->store('inspeksi', 'public');
        }
        if ($foto) $d['foto'] = $foto;

        $inspeksi->items()->create($d);
        return back()->with('ok','Item pemeriksaan ditambahkan.');
    }

    public function destroyItem(InspectionItem $item)
    {
        $item->delete();
        return back()->with('ok','Item dihapus.');
    }

    /** Naikkan temuan menjadi Hazard Report */
    public function angkat(InspectionItem $item)
    {
        abort_if($item->hazard_report_id, 409, 'Temuan ini sudah dinaikkan.');

        $ins = $item->inspection;
        $me  = auth()->user();

        $r = HazardReport::create([
            'kode'               => HazardReport::kodeBaru(),
            'user_id'            => $me->id,
            'pelapor_nama'       => $me->name,
            'pelapor_nrp'        => $me->employee_id,
            'pelapor_perusahaan' => $me->company?->name,
            'pelapor_departemen' => $me->department,
            'pelapor_jabatan'    => $me->position,
            'company_id'         => $ins->company_id,
            'terlapor'           => $ins->company?->name,
            'tanggal'            => $ins->tanggal,
            'lokasi'             => $ins->lokasi,
            'risiko'             => $item->risiko ?: 'Sedang',
            'kategori'           => 'Hasil Inspeksi',
            'deskripsi'          => $item->temuan ?: $item->uraian,
            'rekomendasi'        => $item->tindakan,
            'status'             => 'Open',
            'foto'               => $item->foto,
        ]);

        $item->update(['hazard_report_id' => $r->id]);
        ActivityLog::write('Naikkan temuan inspeksi', $ins->kode.' → '.$r->kode, 'hazrep');

        return redirect()->route('hazard.show', $r)->with('ok','Temuan dinaikkan menjadi '.$r->kode.'.');
    }

    /* ---------- KPI Inspeksi (aturan sama dengan Hazard Report) ---------- */
    public function kpi(Request $request)
    {
        $bulan = $request->get('bulan');

        $inspeksi = Inspection::with('inspectors')
            ->when($bulan, fn($b) => $b->whereRaw(Db::ym('tanggal') . ' = ?', [$bulan]))->get();

        $bulanAktif = $bulan ? 1 : max(1, Inspection::selectRaw(Db::ym('tanggal') . ' as b')
                        ->whereNotNull('tanggal')->distinct()->count());

        $perOrang = [];
        foreach ($inspeksi as $ins) {
            foreach ($ins->inspectors as $p) {
                $key = $p->user_id ?: mb_strtolower($p->nama);
                $perOrang[$key] ??= [
                    'nama' => $p->nama, 'jabatan' => $p->jabatan,
                    'gol' => Hazard::golongan($p->jabatan),
                    'target' => Hazard::target($p->jabatan) * $bulanAktif,
                    'aktual' => 0,
                ];
                $perOrang[$key]['aktual']++;
            }
        }
        uasort($perOrang, fn($a,$b) => $b['aktual'] <=> $a['aktual']);

        $perGolongan = [];
        foreach ($perOrang as $o) {
            $g = $o['gol'];
            $perGolongan[$g] ??= ['target'=>0,'aktual'=>0,'orang'=>0,'tercapai'=>0];
            $perGolongan[$g]['target'] += $o['target'];
            $perGolongan[$g]['aktual'] += $o['aktual'];
            $perGolongan[$g]['orang']++;
            if ($o['aktual'] >= $o['target']) $perGolongan[$g]['tercapai']++;
        }

        // ringkasan temuan
        $items = InspectionItem::whereIn('inspection_id', $inspeksi->pluck('id'))->get();

        return view('inspeksi.kpi', [
            'bulan' => $bulan, 'bulanAktif' => $bulanAktif,
            'perOrang' => $perOrang, 'perGolongan' => $perGolongan,
            'total' => $inspeksi->count(),
            'temuan' => [
                'total'  => $items->count(),
                'sesuai' => $items->where('kondisi','Sesuai')->count(),
                'tidak'  => $items->where('kondisi','Tidak Sesuai')->count(),
                'naik'   => $items->whereNotNull('hazard_report_id')->count(),
            ],
            'bulanOpsi' => Inspection::selectRaw(Db::ym('tanggal') . ' as b')
                            ->whereNotNull('tanggal')->distinct()->orderByDesc('b')->pluck('b'),
        ]);
    }

    private function v(Request $r): array
    {
        $d = $r->validate([
            'template_id' => ['nullable','exists:inspection_templates,id'],
            'judul'       => ['required','string','max:200'],
            'jenis'       => ['nullable','in:Harian,Mingguan,Bulanan,Khusus'],
            'company_id'  => ['nullable','exists:companies,id'],
            'lokasi'      => ['nullable','string','max:200'],
            'tanggal'     => ['required','date'],
            'pelaksana'   => ['nullable','string','max:150'],
            'status'      => ['nullable','in:Berjalan,Selesai'],
            'catatan'     => ['nullable','string','max:2000'],
        ]);
        $d['status'] = $d['status'] ?? 'Berjalan';
        return $d;
    }
}
