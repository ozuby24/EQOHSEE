<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\{Company, User};
use App\Support\{AturanSandi, MasukPihakKetiga, Turnstile};
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\{Rules, ValidationException};
use Inertia\Inertia;

/**
 * Pendaftaran akun — melengkapi data man power sejak awal
 * (NRP, jabatan, departemen, perusahaan) agar laporan Hazard/Inspeksi
 * bisa terisi otomatis dan KPI terhitung benar.
 */
class RegisteredUserController extends Controller
{
    public function create(Request $request)
    {
        return Inertia::render('Auth/Register', [
            /* Tidak ada pilihan perusahaan: akun baru OTOMATIS masuk
               perusahaan demo (perusahaanDemoBawaan). Halaman ini hanya
               perlu tahu apakah perusahaan demo itu ada, untuk memilih
               kalimat petunjuknya — nama perusahaan mana pun tidak
               dikirim ke pengunjung yang belum masuk. */
            'adaDemo'     => self::perusahaanDemoBawaan() !== null,
            'departments' => \App\Support\Hazard::DEPARTEMEN,
            'positions'   => \App\Support\Hazard::JABATAN,

            /* Kunci SITUS Turnstile — memang dirancang publik; rahasianya
               tidak pernah meninggalkan server. null berarti fiturnya
               mati, dan halaman ini menggambar dirinya seperti sebelum
               fitur itu ada. */
            'turnstile'   => Turnstile::kunciSitus(),
            'tindakan'    => Turnstile::TINDAKAN['daftar'],
            'sandiMinimal' => AturanSandi::MINIMAL,

            /* Daftar dengan Google/Microsoft: hanya penyedia yang
               kredensialnya terpasang, sama seperti di halaman masuk. */
            'sso'         => MasukPihakKetiga::tersedia(),

            /* Identitas yang baru dibuktikan penyedia dan menunggu
               formulir ini diselesaikan — null bila tidak ada. Surelnya
               ditampilkan terkunci, dan kolom sandinya tidak digambar. */
            'identitas'   => MasukPihakKetiga::identitas($request),
        ]);
    }

    public function store(Request $request)
    {
        /* Daftar dengan Google/Microsoft: surelnya milik penyedia, bukan
           isian formulir — kolom surel yang dikirim diabaikan, jadi
           identitas yang terbukti tidak dapat ditukar dengan surel lain. */
        $identitas = MasukPihakKetiga::identitas($request);

        if ($identitas === null && $request->boolean('lewat_sso')) {
            throw ValidationException::withMessages([
                'email' => __('Waktu untuk menyelesaikan pendaftaran dengan Google atau Microsoft sudah habis. Ulangi lewat tombolnya.'),
            ]);
        }

        if ($identitas !== null) {
            $request->merge(['email' => $identitas['email']]);
        }

        $data = $request->validate([
            'name'        => ['required','string','max:255'],
            'email'       => ['required','string','lowercase','email','max:255','unique:users,email'],
            /* Tanpa sandi untuk akun dari penyedia: masuknya lewat tombol
               penyedia itu. Sandi dapat dibuat kapan saja lewat Lupa kata
               sandi, yang mengirim tautan ke surel yang sama. */
            'password'    => $identitas !== null
                ? ['exclude']
                : ['required','confirmed', Rules\Password::defaults()],
            'employee_id' => ['nullable','string','max:50'],
            'position'    => ['required','string','max:100'],
            'department'  => ['nullable','string','max:100'],

            /* Pendaftaran terbuka untuk siapa saja yang membuka
               halamannya, dan itu memang disengaja — pekerja baru di site
               mendaftarkan dirinya sendiri. Yang tidak disengaja adalah
               skrip yang memakai pintu yang sama untuk menanam ratusan
               akun, masing-masing memicu satu surel verifikasi dari
               server ini. */
            Turnstile::KOLOM => Turnstile::aturan(Turnstile::TINDAKAN['daftar']),
        ], AturanSandi::pesan());

        /* Tokennya sengaja TIDAK dibuang dari $data di sini.
           User::$fillable adalah daftar putih, jadi cf-turnstile-response
           tidak akan pernah sampai ke perintah insert — dan membuangnya
           lagi di sini hanya menambah satu baris yang tampak menjaga
           sesuatu padahal tidak, sehingga tidak ada cara membuktikannya
           masih bekerja. Yang menjaganya adalah daftar putih itu, dan
           itulah yang dijaga uji. */

        $data['lms_role'] = 'trainee';
        $data['active']   = true;

        /* Perusahaannya ditetapkan DI SINI, tidak pernah dari isian:
           company_id tidak ada di aturan validasi, jadi nilai yang
           dikirim permintaan buatan tidak pernah sampai ke $data. Dulu
           halaman ini memuat SELURUH perusahaan, dan siapa pun dapat
           menempatkan dirinya di perusahaan pelanggan lalu membaca
           datanya. Kini akun baru selalu masuk perusahaan demo;
           memindahkannya ke perusahaan sungguhan adalah keputusan
           administrator, di Kelola Pengguna. Tanpa perusahaan demo sama
           sekali, akunnya menunggu ditempatkan administrator. */
        $data['company_id'] = self::perusahaanDemoBawaan();

        if ($identitas !== null) {
            /* Sandi acak yang tidak pernah diketahui siapa pun — kolomnya
               wajib terisi, dan sandi kosong akan cocok dengan isian
               kosong di formulir masuk. */
            $data['password'] = Str::password(48);
        }

        $user = User::create($data);

        if ($identitas !== null) {
            /* Surelnya sudah dibuktikan penyedia, jadi tidak ada kode
               verifikasi yang dikirim. Identitasnya dibuang: sekali pakai. */
            MasukPihakKetiga::lupakanIdentitas($request);
            $user->markEmailAsVerified();
            Auth::login($user);

            return redirect()->route('dashboard');
        }

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

    /**
     * Perusahaan demo tempat akun baru dimulai: PT DEMO (kode DEMO) bila
     * ada — perusahaan contoh utama dari `php artisan demo:pasang` —
     * selain itu perusahaan demo tertua. null bila tidak ada satu pun.
     */
    public static function perusahaanDemoBawaan(): ?int
    {
        $id = Company::query()->withoutGlobalScopes()->where('demo', true)
            ->orderByRaw("CASE WHEN code = 'DEMO' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
