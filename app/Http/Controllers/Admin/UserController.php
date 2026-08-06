<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{ActivityLog, Company, User};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        return view('admin.users.index', compact('users','q'));
    }

    public function create()
    {
        return view('admin.users.form', ['user' => new User(), 'companies' => Company::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, null);
        $data['password'] = $data['password'] ?: 'password';
        $user = User::create($data);
        ActivityLog::write('Buat pengguna', $user->name.' ('.$user->email.')');

        return redirect()->route('admin.users.index')->with('ok', 'Pengguna dibuat.');
    }

    public function edit(User $user)
    {
        return view('admin.users.form', ['user' => $user, 'companies' => Company::orderBy('name')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user->id);
        if (empty($data['password'])) unset($data['password']);

        // Pengaman: admin terakhir tidak boleh menurunkan dirinya sendiri
        if ($user->id === auth()->id() && empty($data['is_admin']) && User::where('is_admin', true)->count() <= 1) {
            return back()->withErrors(['is_admin' => 'Tidak dapat menonaktifkan admin terakhir.']);
        }

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
            'audit_role'  => ['nullable', Rule::in(['auditor','company'])],
            'company_id'  => ['nullable','exists:companies,id'],
            'employee_id' => ['nullable','string','max:50'],
            'position'    => ['nullable','string','max:100'],
            'department'  => ['nullable','string','max:100'],
            'phone'       => ['nullable','string','max:30'],
            'active'      => ['nullable','boolean'],
        ]);
    }
}
