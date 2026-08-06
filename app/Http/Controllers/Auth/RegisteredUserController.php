<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{Company, User};
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;

/**
 * Pendaftaran akun — melengkapi data man power sejak awal
 * (NRP, jabatan, departemen, perusahaan) agar laporan Hazard/Inspeksi
 * bisa terisi otomatis dan KPI terhitung benar.
 */
class RegisteredUserController extends Controller
{
    public function create()
    {
        return view('auth.register', ['companies' => Company::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required','string','max:255'],
            'email'       => ['required','string','lowercase','email','max:255','unique:users,email'],
            'password'    => ['required','confirmed', Rules\Password::defaults()],
            'employee_id' => ['nullable','string','max:50'],
            'position'    => ['required','string','max:100'],
            'department'  => ['nullable','string','max:100'],
            'company_id'  => ['nullable','exists:companies,id'],
        ]);

        $data['lms_role'] = 'trainee';
        $data['active']   = true;

        $user = User::create($data);

        event(new Registered($user));
        Auth::login($user);

        return redirect(RouteServiceProvider::HOME ?? '/dashboard');
    }
}
