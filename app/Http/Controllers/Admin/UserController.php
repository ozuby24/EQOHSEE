<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Company, User};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q'));
        $users = User::with('company')
            ->when($q, fn($b) => $b->where(fn($w) =>
                $w->where('name','like',"%$q%")->orWhere('email','like',"%$q%")))
            ->orderByDesc('is_admin')->orderBy('name')
            ->paginate(20)->withQueryString();

        return Inertia::render('Admin/Pengguna/Daftar', [
            'judul'    => 'Kelola Pengguna',
            'subjudul' => 'Peran, akses, dan data pegawai',

            // Kuncinya BUKAN 'pengguna': nama itu sudah dipakai
            // HandleInertiaRequests untuk pengguna yang sedang masuk, dan
            // prop halaman menimpanya. Sempat terjadi — bilah atas
            // memanggil pengguna.nama atas larik pengguna, seluruh
            // halamannya gagal dirender, dan yang tampak hanya layar
            // kosong tanpa pesan apa pun.
            'daftar' => array_map(fn (User $u) => [
                'id'         => $u->id,
                'nama'       => $u->name,
                'email'      => $u->email,
                'inisial'    => mb_strtoupper(mb_substr($u->name, 0, 1)),
                'admin'      => (bool) $u->is_admin,
                'lmsRole'    => $u->lms_role,
                // Label, bukan kunci: 'company' terbaca sebagai nama perusahaan.
                'auditRole'  => match ($u->audit_role) {
                    User::ROLE_AUDITOR => 'Auditor',
                    User::ROLE_AUDITI  => 'Auditi',
                    default            => $u->audit_role,
                },
                'ohseRole'   => $u->ohse_role,
                'jabatan'    => $u->position ?: null,
                'departemen' => $u->department ?: null,
                'aktif'      => (bool) $u->active,
                'perusahaan' => $u->company?->name,
                'diri'       => $u->id === auth()->id(),
                'urlUbah'    => route('admin.users.edit', $u),
                'urlHapus'   => route('admin.users.destroy', $u),
            ], $users->items()),

            'halaman' => $this->halaman($users),
            'q'       => $q,
            'tautan'  => ['daftar' => route('admin.users.index'), 'buat' => route('admin.users.create')],
        ]);
    }

    public function create()
    {
        return $this->formulir(new User());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $data['password'] = $data['password'] ?: 'password';
        $this->pastikanKuotaAuditi($data);
        $user = User::create($data);
        ActivityLog::write('Buat pengguna', $user->name.' ('.$user->email.')');

        return redirect()->route('admin.users.index')->with('ok', 'Pengguna dibuat.');
    }

    public function edit(User $user)
    {
        return $this->formulir($user);
    }

    /** Formulir pengguna, dipakai bersama oleh create dan edit. */
    private function formulir(User $u)
    {
        return Inertia::render('Admin/Pengguna/Form', [
            'judul'    => $u->exists ? 'Edit Pengguna' : 'Pengguna Baru',
            'subjudul' => $u->exists ? $u->email : 'Buat akun baru beserta peran dan aksesnya',

            'tersimpan' => $u->exists,

            'awal' => [
                'name'        => (string) ($u->name ?? ''),
                'email'       => (string) ($u->email ?? ''),
                'password'    => '',
                'lms_role'    => (string) ($u->lms_role ?? ''),
                'audit_role'  => (string) ($u->audit_role ?? ''),
                'ohse_role'   => (string) ($u->ohse_role ?? ''),
                'company_id'  => $u->company_id ? (string) $u->company_id : '',
                'employee_id' => (string) ($u->employee_id ?? ''),
                'position'    => (string) ($u->position ?? ''),
                'department'  => (string) ($u->department ?? ''),
                'phone'       => (string) ($u->phone ?? ''),
                'is_admin'    => (bool) $u->is_admin,
                'active'      => $u->exists ? (bool) $u->active : true,
            ],

            'opsi' => [
                'lms'   => [
                    ['nilai' => 'trainee', 'label' => 'Peserta'],
                    ['nilai' => 'trainer', 'label' => 'Trainer'],
                    ['nilai' => 'ktt',     'label' => 'KTT'],
                ],
                'audit' => [
                    ['nilai' => User::ROLE_AUDITOR, 'label' => User::ROLES[User::ROLE_AUDITOR]],
                    ['nilai' => User::ROLE_AUDITI,  'label' => User::ROLES[User::ROLE_AUDITI]],
                ],
                'ohse' => [
                    ['nilai' => 'ohse',      'label' => 'Tim OHSE — memutuskan'],
                    ['nilai' => 'paramedis', 'label' => 'Paramedis — memaraf hasil MCU'],
                ],
                'perusahaan' => Company::orderBy('name')->get()
                    ->map(fn ($c) => ['nilai' => (string) $c->id, 'label' => $c->name])->all(),
                'jabatan'    => \App\Support\Hazard::JABATAN,
                'departemen' => \App\Support\Hazard::DEPARTEMEN,
            ],

            /* Kuota auditi audit SMKP (Aurorasys): auditi aktif per
               perusahaan, tanpa akun yang sedang diubah. */
            'auditiAktif' => (object) $this->auditiAktif($u->exists ? $u : null),
            'maksAuditi'  => User::MAKS_AUDITI,

            'tautan' => [
                'simpan' => $u->exists ? route('admin.users.update', $u) : route('admin.users.store'),
                'batal'  => route('admin.users.index'),
            ],
        ]);
    }

    /**
     * Batas akun auditi aktif per perusahaan (Aurorasys, User::MAKS_AUDITI).
     * Diperiksa hanya bila simpanan ini MENAMBAH auditi aktif di perusahaan
     * tujuannya — akun baru, pindah perusahaan, diaktifkan lagi, atau
     * berganti peran. Mengubah data akun yang sudah terhitung tetap boleh
     * meski kuotanya telanjur terlampaui.
     *
     * @param array<string,mixed> $d data tervalidasi
     */
    private function pastikanKuotaAuditi(array $d, ?User $lama = null): void
    {
        $baru = [
            'is_admin'   => array_key_exists('is_admin', $d) ? (bool) $d['is_admin'] : (bool) $lama?->is_admin,
            'audit_role' => array_key_exists('audit_role', $d) ? $d['audit_role'] : $lama?->audit_role,
            // Kolom `active` berdefault aktif: akun baru tanpa isian terhitung aktif.
            'active'     => array_key_exists('active', $d) && $d['active'] !== null ? (bool) $d['active'] : (bool) ($lama?->active ?? true),
            'company_id' => array_key_exists('company_id', $d) ? $d['company_id'] : $lama?->company_id,
        ];
        $dihitung = fn (array $u) => !$u['is_admin'] && $u['audit_role'] === User::ROLE_AUDITI && $u['active'] && $u['company_id'];

        if (!$dihitung($baru)) return;
        if ($lama && $dihitung([
            'is_admin' => (bool) $lama->is_admin, 'audit_role' => $lama->audit_role,
            'active' => (bool) $lama->active, 'company_id' => $lama->company_id,
        ]) && (int) $lama->company_id === (int) $baru['company_id']) return;

        $terpakai = User::where('company_id', $baru['company_id'])->where('is_admin', false)
            ->where('audit_role', User::ROLE_AUDITI)->where('active', true)
            ->when($lama, fn ($q) => $q->whereKeyNot($lama->id))->count();

        if ($terpakai >= User::MAKS_AUDITI) {
            $nama = Company::whereKey($baru['company_id'])->value('name');
            throw ValidationException::withMessages(['company_id' => sprintf(
                '%s sudah memiliki %d akun auditi aktif — batasnya %d akun per IUP/PJP. Nonaktifkan salah satunya lebih dulu.',
                $nama, $terpakai, User::MAKS_AUDITI,
            )]);
        }
    }

    /** Jumlah auditi aktif per perusahaan, tanpa akun yang sedang diubah. @return array<int,int> */
    private function auditiAktif(?User $kecuali): array
    {
        return User::where('is_admin', false)->where('audit_role', User::ROLE_AUDITI)->where('active', true)
            ->whereNotNull('company_id')
            ->when($kecuali?->id, fn ($q) => $q->whereKeyNot($kecuali->id))
            ->selectRaw('company_id, COUNT(*) AS n')->groupBy('company_id')->pluck('n', 'company_id')
            ->map(fn ($n) => (int) $n)->all();
    }

    /** Bentuk penomoran halaman yang sama untuk seluruh daftar Inertia. */
    private function halaman($paginator): array
    {
        return [
            'kini'   => $paginator->currentPage(),
            'akhir'  => $paginator->lastPage(),
            'total'  => $paginator->total(),
            'tautan' => array_map(fn ($t) => [
                'label' => $t['label'], 'url' => $t['url'], 'aktif' => (bool) $t['active'],
            ], $paginator->linkCollection()->all()),
        ];
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user->id);
        if (empty($data['password'])) unset($data['password']);

        // Pengaman: admin terakhir tidak boleh menurunkan dirinya sendiri
        if ($user->id === auth()->id() && empty($data['is_admin']) && User::where('is_admin', true)->count() <= 1) {
            return back()->withErrors(['is_admin' => 'Tidak dapat menonaktifkan admin terakhir.']);
        }

        $this->pastikanKuotaAuditi($data, $user);

        $user->update($data);
        ActivityLog::write('Ubah pengguna', $user->name);

        return redirect()->route('admin.users.index')->with('ok', 'Pengguna diperbarui.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['user' => 'Tidak dapat menghapus akun yang sedang dipakai.']);
        }
        if ($user->is_admin && User::where('is_admin', true)->count() <= 1) {
            return back()->withErrors(['user' => 'Tidak dapat menghapus admin terakhir.']);
        }
        $nama = $user->name;
        $user->delete();
        ActivityLog::write('Hapus pengguna', $nama);

        return back()->with('ok', 'Pengguna dihapus.');
    }

    private function validated(Request $request, ?int $id): array
    {
        return $request->validate([
            'name'        => ['required','string','max:150'],
            'email'       => ['required','email','max:150', Rule::unique('users')->ignore($id)],
            'password'    => [$id ? 'nullable' : 'nullable', 'string','min:8'],
            'is_admin'    => ['nullable','boolean'],
            'lms_role'    => ['nullable', Rule::in(['trainee','trainer','ktt'])],
            'audit_role'  => ['nullable', Rule::in([User::ROLE_AUDITOR, User::ROLE_AUDITI])],

            /* Peran di dalam tim keselamatan & kesehatan kerja. Dipisah
               dari kedua peran di atas dengan sengaja — lihat
               App\Support\Tahap.
        
               'ohse'      menerbitkan kartu masuk dan meloloskan MCU;
               'paramedis' membaca hasil pemeriksaan dan memaraf tahapnya.
        
               Keduanya SALING MENIADAKAN, dan itu memang dikehendaki:
               tahap paramedis ada supaya yang membaca hasil pemeriksaan
               bukan orang yang memutuskan kelayakannya. */
            'ohse_role'   => ['nullable', Rule::in(['ohse', 'paramedis'])],
            /* Auditi audit SMKP tanpa perusahaan tidak menjangkau audit
               mana pun dan tidak menerima pengingat (Aurorasys). */
            'company_id'  => [Rule::requiredIf(fn () => $request->input('audit_role') === User::ROLE_AUDITI && !$request->boolean('is_admin')),
                              'nullable','exists:companies,id'],
            'employee_id' => ['nullable','string','max:50'],
            'position'    => ['nullable','string','max:100'],
            'department'  => ['nullable','string','max:100'],
            'phone'       => ['nullable','string','max:30'],
            'active'      => ['nullable','boolean'],
        ], [
            'company_id.required' => 'Pilih perusahaan (IUP/PJP) tempat auditi ini menyampaikan bukti dan tindak lanjut.',
        ]);
    }
}
