<?php

namespace App\Http\Controllers;

use App\Models\{ActivityLog, Signatory};
use Illuminate\Http\Request;
use Inertia\Inertia;

class SignatoryController extends Controller
{
    public function index()
    {
        return Inertia::render('PenandaTangan/Daftar', [
            'judul'    => 'Penanda Tangan Sertifikat',
            'subjudul' => 'Nama yang tercetak pada sertifikat yang diterbitkan',

            'penandaTangan' => Signatory::orderByDesc('is_active')->orderBy('name')->get()
                ->map(fn (Signatory $s) => [
                    'id'        => $s->id,
                    'nama'      => $s->name,
                    'jabatan'   => (string) ($s->title ?? ''),
                    'aktif'     => (bool) $s->is_active,
                    'tandaTangan' => $s->signature ? asset('storage/'.$s->signature) : null,
                    'urlSimpan' => route('signatories.update', $s),
                    'urlHapus'  => route('signatories.destroy', $s),
                ])->all(),

            'tautan' => ['tambah' => route('signatories.store')],
        ]);
    }

    public function store(Request $r)
    {
        Signatory::create($this->v($r));
        ActivityLog::write('Tambah penanda tangan', $r->input('name'));
        return back()->with('ok', 'Penanda tangan ditambahkan.');
    }

    public function update(Request $r, Signatory $signatory)
    {
        $signatory->update($this->v($r));
        return back()->with('ok', 'Penanda tangan diperbarui.');
    }

    public function destroy(Signatory $signatory)
    {
        $signatory->delete();
        return back()->with('ok', 'Penanda tangan dihapus.');
    }

    private function v(Request $r): array
    {
        $d = $r->validate([
            'name'      => ['required','string','max:150'],
            'title'     => ['nullable','string','max:150'],
            'is_active' => ['nullable','boolean'],
            'signature' => ['nullable','image','max:1024'],
        ]);
        $d['is_active'] = (bool) ($d['is_active'] ?? false);   // kolom NOT NULL

        if ($r->hasFile('signature')) {
            $d['signature'] = $r->file('signature')->store('signatures', 'public');
        } else {
            unset($d['signature']);
        }
        return $d;
    }
}
