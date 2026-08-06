<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, InspectionTemplate, InspectionTemplateItem};
use App\Support\Hazard;
use Illuminate\Http\Request;

class InspectionTemplateController extends Controller
{
    public function index()
    {
        return view('inspeksi.template.index', [
            'templates' => InspectionTemplate::withCount(['items','inspections'])->orderBy('nama')->get(),
        ]);
    }

    public function create() { return view('inspeksi.template.form', ['template' => new InspectionTemplate()]); }

    public function store(Request $r)
    {
        $t = InspectionTemplate::create($this->v($r));
        ActivityLog::write('Buat jenis inspeksi', $t->nama, 'hazrep');
        return redirect()->route('inspeksi.template.edit', $t)->with('ok','Jenis inspeksi dibuat. Tambahkan parameter pemeriksaan.');
    }

    public function edit(InspectionTemplate $template)
    {
        $template->load('items');
        return view('inspeksi.template.form', compact('template'));
    }

    public function update(Request $r, InspectionTemplate $template)
    {
        $template->update($this->v($r));
        return back()->with('ok','Jenis inspeksi diperbarui.');
    }

    public function destroy(InspectionTemplate $template)
    {
        $nama = $template->nama;
        $template->delete();
        ActivityLog::write('Hapus jenis inspeksi', $nama, 'hazrep');
        return redirect()->route('inspeksi.template.index')->with('ok','Jenis inspeksi dihapus.');
    }

    /* ---------- Parameter ---------- */
    public function storeItem(Request $r, InspectionTemplate $template)
    {
        $d = $r->validate([
            'kelompok'       => ['nullable','string','max:100'],
            'uraian'         => ['required','string','max:300'],
            'acuan'          => ['nullable','string','max:200'],
            'risiko_default' => ['nullable','in:Rendah,Sedang,Tinggi'],
        ]);
        $d['order_index'] = (int) $template->items()->max('order_index') + 1;
        $template->items()->create($d);
        return back()->with('ok','Parameter ditambahkan.');
    }

    public function destroyItem(InspectionTemplateItem $item)
    {
        $item->delete();
        return back()->with('ok','Parameter dihapus.');
    }

    /** Salin seluruh parameter dari jenis lain */
    public function salin(Request $r, InspectionTemplate $template)
    {
        $sumber = InspectionTemplate::with('items')->findOrFail($r->input('sumber_id'));
        $n = (int) $template->items()->max('order_index');
        foreach ($sumber->items as $it) {
            $template->items()->create([
                'kelompok' => $it->kelompok, 'uraian' => $it->uraian, 'acuan' => $it->acuan,
                'risiko_default' => $it->risiko_default, 'order_index' => ++$n,
            ]);
        }
        return back()->with('ok','Parameter disalin dari "'.$sumber->nama.'".');
    }

    private function v(Request $r): array
    {
        $d = $r->validate([
            'nama'      => ['required','string','max:200'],
            'jenis'     => ['nullable','in:Harian,Mingguan,Bulanan,Khusus'],
            'kategori'  => ['nullable','string','max:100'],
            'deskripsi' => ['nullable','string','max:1000'],
            'is_active' => ['nullable','boolean'],
        ]);
        $d['is_active'] = (bool) ($d['is_active'] ?? false);
        return $d;
    }
}
