<?php

namespace App\Http\Controllers;

use App\Models\Procedure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ProcedureController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));

        $procedures = Procedure::when($q, fn ($b) => $b->where('title', 'like', "%$q%")
                                                       ->orWhere('code', 'like', "%$q%"))
            ->orderBy('position')->paginate(20)->withQueryString();

        return Inertia::render('Prosedur/Daftar', [
            'judul'    => 'Prosedur & SOP',
            'subjudul' => 'Daftar prosedur kerja beserta tautan dokumennya',

            'prosedur' => array_map(fn (Procedure $p) => [
                'id'        => $p->id,
                'kode'      => $p->code ?: null,
                'judul'     => $p->title,
                'kategori'  => $p->category ?: null,
                'keterangan'=> $p->description ?: null,
                'url'       => $p->url ?: null,
                'urlUbah'   => route('procedures.edit', $p),
                'urlHapus'  => route('procedures.destroy', $p),
            ], $procedures->items()),

            'halaman' => [
                'kini'   => $procedures->currentPage(),
                'akhir'  => $procedures->lastPage(),
                'total'  => $procedures->total(),
                'tautan' => array_map(fn ($t) => [
                    'label' => $t['label'], 'url' => $t['url'], 'aktif' => (bool) $t['active'],
                ], $procedures->linkCollection()->all()),
            ],

            'q'         => $q,
            'bolehUbah' => Gate::allows('admin'),
            'tautan'    => ['daftar' => route('procedures.index'), 'buat' => route('procedures.create')],
        ]);
    }

    public function create()
    {
        return $this->formulir(new Procedure());
    }

    public function store(Request $r)
    {
        Procedure::create($this->v($r));

        return redirect()->route('procedures.index')->with('ok', 'Prosedur dibuat.');
    }

    public function edit(Procedure $procedure)
    {
        return $this->formulir($procedure);
    }

    public function update(Request $r, Procedure $procedure)
    {
        $procedure->update($this->v($r));

        return redirect()->route('procedures.index')->with('ok', 'Prosedur diperbarui.');
    }

    public function destroy(Procedure $procedure)
    {
        $procedure->delete();

        return back()->with('ok', 'Prosedur dihapus.');
    }

    /** Formulir prosedur, dipakai bersama oleh create dan edit. */
    private function formulir(Procedure $p)
    {
        return Inertia::render('Prosedur/Form', [
            'judul'    => $p->exists ? 'Edit Prosedur' : 'Prosedur Baru',
            'subjudul' => $p->exists ? $p->title : 'Daftarkan prosedur kerja baru',

            'tersimpan' => $p->exists,

            'awal' => [
                'code'        => (string) ($p->code ?? ''),
                'title'       => (string) ($p->title ?? ''),
                'category'    => (string) ($p->category ?? ''),
                'description' => (string) ($p->description ?? ''),
                'url'         => (string) ($p->url ?? ''),
                'position'    => (string) ($p->position ?: 1),
            ],

            'tautan' => [
                'simpan' => $p->exists ? route('procedures.update', $p) : route('procedures.store'),
                'batal'  => route('procedures.index'),
            ],
        ]);
    }

    private function v(Request $r): array
    {
        $d = $r->validate([
            'code'        => ['nullable', 'string', 'max:50'],
            'title'       => ['required', 'string', 'max:200'],
            'category'    => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'url'         => ['nullable', 'url', 'max:500'],
            'position'    => ['nullable', 'integer', 'min:1'],
        ]);

        $d['position'] = $d['position'] ?? 1;   // kolom NOT NULL berdefault

        return $d;
    }
}
