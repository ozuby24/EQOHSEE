<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Company, TpkkpAssessment, User};
use Illuminate\Http\Request;
use Inertia\Inertia;

class CompanyController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $companies = Company::withCount('users')
            ->when($q, fn($b) => $b->where('name','like',"%$q%")->orWhere('code','like',"%$q%"))
            ->orderBy('name')->paginate(20)->withQueryString();

        return Inertia::render('Admin/Perusahaan/Daftar', [
            'judul'    => 'Kelola Perusahaan',
            'subjudul' => 'Profil, kendali dokumen, dan penanggung jawab',

            'perusahaan' => array_map(fn (Company $c) => [
                'id'         => $c->id,
                'nama'       => $c->name,
                'kode'       => $c->code ?: null,
                'inisial'    => mb_strtoupper(mb_substr($c->code ?: $c->name, 0, 2)),
                'logo'       => $c->logo ? asset('storage/'.$c->logo) : null,
                'risiko'     => $c->risk_class,
                'izin'       => $c->izin_type ?: null,
                'komoditas'  => $c->commodity ?: null,
                'lokasi'     => $c->location ?: null,
                'ktt'        => $c->ktt ?: null,
                'pjo'        => $c->pjo ?: null,
                'pekerja'    => $c->totalWorkers(),
                'pengguna'   => $c->users_count,
                'urlUbah'    => route('admin.companies.edit', $c),
                'urlHapus'   => route('admin.companies.destroy', $c),
                'urlTpkkp'   => route('tpkkp.index', ['company' => $c->id]),
            ], $companies->items()),

            'halaman' => [
                'kini'   => $companies->currentPage(),
                'akhir'  => $companies->lastPage(),
                'total'  => $companies->total(),
                'tautan' => array_map(fn ($t) => [
                    'label' => $t['label'], 'url' => $t['url'], 'aktif' => (bool) $t['active'],
                ], $companies->linkCollection()->all()),
            ],

            'q'      => $q,
            'tautan' => ['daftar' => route('admin.companies.index'), 'buat' => route('admin.companies.create')],
        ]);
    }

    public function create() { return $this->formulir(new Company()); }

    public function store(Request $r)
    {
        $c = Company::create($this->v($r));
        // penilaian TPKKP tidak lagi dibuat per perusahaan
        ActivityLog::write('Tambah perusahaan', $c->name);

        return redirect()->route('admin.companies.index')->with('ok', 'Perusahaan ditambahkan.');
    }

    public function edit(Company $company) { return $this->formulir($company); }

    /** Formulir perusahaan, dipakai bersama oleh create dan edit. */
    private function formulir(Company $c)
    {
        $teks = fn ($v) => (string) ($v ?? '');

        return Inertia::render('Admin/Perusahaan/Form', [
            'judul'    => $c->exists ? 'Edit Perusahaan' : 'Perusahaan Baru',
            'subjudul' => $c->exists ? $c->name : 'Daftarkan perusahaan beserta kendali dokumennya',

            'tersimpan' => $c->exists,

            'awal' => [
                'name'             => $teks($c->name),
                'code'             => $teks($c->code),
                'parent_id'        => $c->parent_id ? (string) $c->parent_id : '',
                'doc_no_prefix'    => $teks($c->doc_no_prefix),
                'divisi'           => $teks($c->divisi),
                'departemen'       => $teks($c->departemen),
                'dept_kode'        => $teks($c->dept_kode),
                'doc_terbit'       => $c->doc_terbit?->format('Y-m-d') ?? '',
                'doc_setuju'       => $c->doc_setuju?->format('Y-m-d') ?? '',
                'doc_revisi'       => (string) ($c->doc_revisi ?? 0),
                'izin_type'        => $teks($c->izin_type),
                'commodity'        => $teks($c->commodity),
                'location'         => $teks($c->location),
                'risk_class'       => $c->risk_class ?: 'Tinggi',
                'address'          => $teks($c->address),
                'ktt'              => $teks($c->ktt),
                'pjo'              => $teks($c->pjo),
                'workers_employee' => (string) ($c->workers_employee ?? 0),
                'workers_sub'      => (string) ($c->workers_sub ?? 0),
                'pic_name'         => $teks($c->pic_name),
                'pic_email'        => $teks($c->pic_email),
                'pic_phone'        => $teks($c->pic_phone),
            ],

            'logo' => $c->logo ? asset('storage/'.$c->logo) : null,

            'opsi' => [
                // Perusahaan tidak boleh menjadi induk dirinya sendiri;
                // rantai yang menunjuk balik membuat pencarian logo induk
                // berputar tanpa henti.
                'induk'  => Company::whereKeyNot($c->id ?? 0)->orderBy('name')->get()
                    ->map(fn ($o) => ['nilai' => (string) $o->id, 'label' => $o->name])->all(),
                'risiko' => ['Rendah', 'Sedang', 'Tinggi'],
            ],

            'contoh' => [
                'prefiks'    => \App\Support\KopDokumen::prefiksDari($c->name ?: 'Perusahaan'),
                'divisi'     => \App\Support\KopDokumen::DIVISI,
                'departemen' => \App\Support\KopDokumen::DEPARTEMEN,
            ],

            'tautan' => [
                'simpan' => $c->exists ? route('admin.companies.update', $c) : route('admin.companies.store'),
                'batal'  => route('admin.companies.index'),
            ],
        ]);
    }

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
            'divisi'           => ['nullable','string','max:150'],
            'departemen'       => ['nullable','string','max:150'],

            /* Bagian OHSE pada FRM/CAM/OHSE/001. Huruf dan angka saja:
               garis miring di dalamnya akan memecah nomornya sendiri. */
            'dept_kode'        => ['nullable','string','max:12','regex:/^[A-Za-z0-9]+$/'],
            'doc_terbit'       => ['nullable','date'],
            'doc_setuju'       => ['nullable','date'],
            'doc_revisi'       => ['nullable','integer','min:0','max:999'],
            'logo'             => ['nullable','image','max:1024'],
        ]);
        if ($r->hasFile('logo')) $d['logo'] = $r->file('logo')->store('logos', 'public');
        else unset($d['logo']);

        // Kolom NOT NULL berdefault: form kosong menjadi null → isi nilai aman.
        $d['parent']           = $d['parent']           ?? '';        // aman di skema lama (NOT NULL) & baru (nullable)
        $d['risk_class']       = $d['risk_class']       ?? 'Tinggi';
        $d['workers_employee'] = $d['workers_employee'] ?? 0;
        $d['workers_sub']      = $d['workers_sub']      ?? 0;
        $d['doc_revisi']       = $d['doc_revisi']       ?? 0;

        return $d;
    }
}
