<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{Company, User};
use App\Support\Turnstile;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

/**
 * Pendaftaran akun — melengkapi data man power sejak awal
 * (NRP, jabatan, departemen, perusahaan) agar laporan Hazard/Inspeksi
 * bisa terisi otomatis dan KPI terhitung benar.
 */
class RegisteredUserController extends Controller
{
    public function create()
    {
        return Inertia::render('Auth/Register', [
            'companies'   => Company::orderBy('name')->get(['id', 'name']),
            'departments' => \App\Support\Hazard::DEPARTEMEN,
            'positions'   => \App\Support\Hazard::JABATAN,

            /* Kunci SITUS Turnstile — memang dirancang publik; rahasianya
               tidak pernah meninggalkan server. null berarti fiturnya
               mati, dan halaman ini menggambar dirinya seperti sebelum
               fitur itu ada. */
            'turnstile'   => Turnstile::kunciSitus(),
            'tindakan'    => Turnstile::TINDAKAN['daftar'],
        ]);
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

            /* Pendaftaran terbuka untuk siapa saja yang membuka
               halamannya, dan itu memang disengaja — pekerja baru di site
               mendaftarkan dirinya sendiri. Yang tidak disengaja adalah
               skrip yang memakai pintu yang sama untuk menanam ratusan
               akun, masing-masing memicu satu surel verifikasi dari
               server ini. */
            Turnstile::KOLOM => Turnstile::aturan(Turnstile::TINDAKAN['daftar']),
        ]);

        /* Tokennya sengaja TIDAK dibuang dari $data di sini.
           User::$fillable adalah daftar putih, jadi cf-turnstile-response
           tidak akan pernah sampai ke perintah insert — dan membuangnya
           lagi di sini hanya menambah satu baris yang tampak menjaga
           sesuatu padahal tidak, sehingga tidak ada cara membuktikannya
           masih bekerja. Yang menjaganya adalah daftar putih itu, dan
           itulah yang dijaga uji. */

        $data['lms_role'] = 'trainee';
        $data['active']   = true;

        $user = User::create($data);

        /* Registered memanggil sendEmailVerificationNotification pada User,
           yang sudah dibajak untuk mengirim kode enam angka. Mengirim
           sendiri di sini akan menghasilkan dua surel untuk satu
           pendaftaran, dan kode pada yang pertama langsung tidak berlaku. */
        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
