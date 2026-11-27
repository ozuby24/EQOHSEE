<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{Company, User};
use App\Support\{AturanSandi, Turnstile};
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\{Rule, Rules};
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
            /* HANYA perusahaan demo.

               Pendaftaran terbuka untuk siapa saja, dan halaman ini dapat
               dibuka tanpa masuk. Dulu daftarnya memuat SELURUH perusahaan:
               nama setiap pelanggan terpampang bagi pengunjung mana pun,
               dan siapa pun dapat menempatkan dirinya di perusahaan
               sungguhan lalu — sesudah surelnya terverifikasi — membaca
               datanya. Akun baru kini hanya dapat memilih perusahaan
               demo; memindahkannya ke perusahaan sungguhan adalah
               keputusan administrator, di Kelola Pengguna. */
            'companies'   => self::perusahaanDemo()->get(['id', 'name']),
            'departments' => \App\Support\Hazard::DEPARTEMEN,
            'positions'   => \App\Support\Hazard::JABATAN,

            /* Kunci SITUS Turnstile — memang dirancang publik; rahasianya
               tidak pernah meninggalkan server. null berarti fiturnya
               mati, dan halaman ini menggambar dirinya seperti sebelum
               fitur itu ada. */
            'turnstile'   => Turnstile::kunciSitus(),
            'tindakan'    => Turnstile::TINDAKAN['daftar'],
            'sandiMinimal' => AturanSandi::MINIMAL,
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
            /* Ditegakkan DI SINI, bukan hanya dengan menyaring daftar di
               layar: permintaan dapat dikirim tanpa layarnya sama sekali.
               Bila ada perusahaan demo, salah satunya wajib dipilih —
               akun tanpa perusahaan hanya melihat halaman kosong. Bila
               tidak ada sama sekali, akunnya menunggu ditempatkan
               administrator. */
            'company_id'  => self::perusahaanDemo()->exists()
                ? ['required', Rule::exists('companies', 'id')->where('demo', true)]
                : ['prohibited'],

            /* Pendaftaran terbuka untuk siapa saja yang membuka
               halamannya, dan itu memang disengaja — pekerja baru di site
               mendaftarkan dirinya sendiri. Yang tidak disengaja adalah
               skrip yang memakai pintu yang sama untuk menanam ratusan
               akun, masing-masing memicu satu surel verifikasi dari
               server ini. */
            Turnstile::KOLOM => Turnstile::aturan(Turnstile::TINDAKAN['daftar']),
        ], AturanSandi::pesan() + [
            'company_id.required'   => __('Pilih perusahaan demo untuk akun baru Anda.'),
            'company_id.exists'     => __('Akun baru hanya dapat memakai perusahaan demo. Administrator yang memindahkan akun Anda ke perusahaan Anda sendiri.'),
            'company_id.prohibited' => __('Perusahaan ditetapkan administrator sesudah akun Anda dibuat.'),
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
        Auth::login($user);

        /* Di lingkungan lokal tidak ada server surel sungguhan (MAIL_MAILER
           biasanya 'log'), jadi kode verifikasi tidak pernah sampai ke
           siapa pun. Supaya orang yang mendaftar untuk uji coba lokal tidak
           tersangkut di halaman verifikasi menunggu surel yang tidak akan
           datang, email langsung ditandai terverifikasi di sini. */
        if (app()->environment('local')) {
            $user->markEmailAsVerified();

            return redirect()->route('dashboard');
        }

        /* Registered memanggil sendEmailVerificationNotification pada User,
           yang sudah dibajak untuk mengirim kode enam angka. Mengirim
           sendiri di sini akan menghasilkan dua surel untuk satu
           pendaftaran, dan kode pada yang pertama langsung tidak berlaku. */
        event(new Registered($user));

        return redirect()->route('verification.notice');
    }

    /** Perusahaan yang boleh dipilih akun baru: hanya yang bertanda demo. */
    private static function perusahaanDemo()
    {
        return Company::query()->withoutGlobalScopes()->where('demo', true)->orderBy('name');
    }
}
