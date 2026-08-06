<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Company, TpkkpAssessment, User};
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $companies = Company::withCount('users')
            ->when($q, fn($b) => $b->where('name','like',"%$q%")->orWhere('code','like',"%$q%"))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('admin.companies.index', compact('companies','q'));
    }

    public function create() { return view('admin.companies.form', ['company' => new Company()]); }

    public function store(Request $r)
    {
        $c = Company::create($this->v($r));
        // penilaian TPKKP tidak lagi dibuat per perusahaan
        ActivityLog::write('Tambah perusahaan', $c->name);

        return redirect()->route('admin.companies.index')->with('ok', 'Perusahaan ditambahkan.');
    }

    public function edit(Company $company) { return view('admin.companies.form', compact('company')); }

    public function update(Request $r, Company $company)
    {
        $company->update($this->v($r));
        ActivityLog::write('Ubah perusahaan', $company->name);
        return redirect()->route('admin.companies.index')->with('ok', 'Perusahaan diperbarui.');
    }

    public function destroy(Company $company)
    {
        if (Company::count() <= 1) {
            return back()->withErrors(['company' => 'Minimal harus ada satu perusahaan.']);
        }
        $nama = $company->name;
        User::where('company_id', $company->id)->update(['company_id' => null]);
        $company->delete();                      // assessment & response ikut terhapus (cascade)
        ActivityLog::write('Hapus perusahaan', $nama);

        return back()->with('ok', 'Perusahaan dihapus. Pengguna terkait dilepas, bukan dihapus.');
    }

    private function v(Request $r): array
    {
        $d = $r->validate([
            'name'             => ['required','string','max:200'],
            'code'             => ['nullable','string','max:30'],
            'parent'           => ['nullable','string','max:150'],
            'parent_id'        => ['nullable','exists:companies,id'],
            'izin_type'        => ['nullable','string','max:150'],
            'commodity'        => ['nullable','string','max:150'],
            'location'         => ['nullable','string','max:200'],
            'address'          => ['nullable','string','max:400'],
            'ktt'              => ['nullable','string','max:150'],
            'pjo'              => ['nullable','string','max:150'],
            'pic_name'         => ['nullable','string','max:150'],
            'pic_email'        => ['nullable','email','max:150'],
            'pic_phone'        => ['nullable','string','max:30'],
            'workers_employee' => ['nullable','integer','min:0'],
            'workers_sub'      => ['nullable','integer','min:0'],
            'risk_class'       => ['nullable','in:Rendah,Sedang,Tinggi'],
            'doc_no_prefix'    => ['nullable','string','max:20'],
            'logo'             => ['nullable','image','max:1024'],
        ]);
        if ($r->hasFile('logo')) $d['logo'] = $r->file('logo')->store('logos', 'public');
        else unset($d['logo']);

        // Kolom NOT NULL berdefault: form kosong menjadi null → isi nilai aman.
        $d['parent']           = $d['parent']           ?? '';        // aman di skema lama (NOT NULL) & baru (nullable)
        $d['risk_class']       = $d['risk_class']       ?? 'Tinggi';
        $d['workers_employee'] = $d['workers_employee'] ?? 0;
        $d['workers_sub']      = $d['workers_sub']      ?? 0;

        return $d;
    }
}
