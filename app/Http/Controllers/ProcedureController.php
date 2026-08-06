<?php
namespace App\Http\Controllers;
use App\Models\Procedure;
use Illuminate\Http\Request;
class ProcedureController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $procedures = Procedure::when($q, fn($b) => $b->where('title','like',"%$q%")->orWhere('code','like',"%$q%"))
            ->orderBy('position')->paginate(20)->withQueryString();
        return view('procedures.index', compact('procedures','q'));
    }
    public function create() { return view('procedures.form', ['procedure' => new Procedure()]); }
    public function store(Request $r) { Procedure::create($this->v($r)); return redirect()->route('procedures.index')->with('ok','Prosedur dibuat.'); }
    public function edit(Procedure $procedure) { return view('procedures.form', compact('procedure')); }
    public function update(Request $r, Procedure $procedure) { $procedure->update($this->v($r)); return redirect()->route('procedures.index')->with('ok','Prosedur diperbarui.'); }
    public function destroy(Procedure $procedure) { $procedure->delete(); return back()->with('ok','Prosedur dihapus.'); }

    private function v(Request $r): array
    {
        $d = $r->validate([
            'code'        => ['nullable','string','max:50'],
            'title'       => ['required','string','max:200'],
            'category'    => ['nullable','string','max:100'],
            'description' => ['nullable','string'],
            'url'         => ['nullable','url','max:500'],
            'position'    => ['nullable','integer','min:1'],
        ]);

        $d['position'] = $d['position'] ?? 1;   // kolom NOT NULL berdefault

        return $d;
    }
}
