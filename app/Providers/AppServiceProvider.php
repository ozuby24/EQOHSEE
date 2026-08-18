<?php

namespace App\Providers;

use App\Listeners\CatatPeristiwaAuth;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\{Failed, Lockout, Login, Logout, PasswordReset};
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Event, Gate, RateLimiter};
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('admin',   fn (User $u) => $u->isAdmin());
        Gate::define('trainer', fn (User $u) => $u->isAdmin() || $u->isTrainer());

        // Seluruh antarmuka dan berkas cetak berbahasa Indonesia, termasuk
        // nama bulan pada kop dokumen terkendali. Disetel di sini agar tidak
        // bergantung pada APP_LOCALE yang berbeda-beda antar server.
        Carbon::setLocale('id');
        CarbonImmutable::setLocale('id');

        /* Jejak akses dipasang pada peristiwa auth, bukan pada
           controller — lihat CatatPeristiwaAuth untuk alasannya. */
        Event::listen(Login::class,         [CatatPeristiwaAuth::class, 'masuk']);
        Event::listen(Failed::class,        [CatatPeristiwaAuth::class, 'gagal']);
        Event::listen(Lockout::class,       [CatatPeristiwaAuth::class, 'terkunci']);
        Event::listen(Logout::class,        [CatatPeristiwaAuth::class, 'keluar']);
        Event::listen(PasswordReset::class, [CatatPeristiwaAuth::class, 'sandiDiatur']);

        $this->batasLaju();
    }

    /**
     * Batas laju pada pintu masuk.
     *
     * LoginRequest sudah punya penjagaannya sendiri: lima percobaan per
     * pasangan (email, IP). Penjagaan itu benar untuk satu jenis
     * serangan — menebak sandi satu akun dari satu tempat — dan buta
     * terhadap jenis yang justru dipakai menyerang aplikasi perusahaan.
     *
     * PENYEMPROTAN. Satu sandi yang paling sering dipakai, dicoba ke
     * seluruh alamat surel karyawan, satu kali masing-masing. Tiap
     * pasangan (email, IP) hanya terpakai satu dari lima jatahnya,
     * sehingga penjagaan yang ada tidak pernah menyala sama sekali —
     * padahal pada seratus akun, satu sandi lemah hampir pasti ada.
     * Karena itu ditambah batas per-IP di sini.
     *
     * Dan sebaliknya, penyerang yang berpindah-pindah IP terhadap SATU
     * akun juga lolos, sebab kuncinya memuat IP. Batas per-akun tanpa IP
     * menutup sisi itu — sengaja lebih longgar, sebab siapa pun dapat
     * mengunci akun orang lain hanya dengan mengetik surelnya, dan
     * penguncian semacam itu adalah gangguan layanan yang dilakukan
     * dengan tangan kosong.
     */
    private function batasLaju(): void
    {
        /* Percobaan masuk dari satu alamat, berapa pun akun yang dituju. */
        RateLimiter::for('masuk', fn (Request $r) => [
            Limit::perMinute(20)->by('ip:'.$r->ip()),
            Limit::perMinute(10)->by('akun:'.Str::lower((string) $r->input('email'))),
        ]);

        /* Pendaftaran. Tanpa batas, satu skrip dapat membuat akun tanpa
           henti — dan tiap akun memicu satu surel keluar, sehingga
           kuota SMTP habis dan surel yang sungguh-sungguh dinantikan
           berhenti terkirim. */
        RateLimiter::for('daftar', fn (Request $r) => Limit::perHour(5)->by($r->ip()));

        /* Permintaan tautan setel ulang. Batasnya per ALAMAT SUREL, bukan
           hanya per IP: yang dirugikan bukan server melainkan orang yang
           kotak masuknya dibanjiri, dan pembanjirnya dapat berpindah IP
           sesuka hati. */
        RateLimiter::for('lupa-sandi', fn (Request $r) => [
            Limit::perMinute(5)->by('ip:'.$r->ip()),
            Limit::perHour(5)->by('surel:'.Str::lower((string) $r->input('email'))),
        ]);

        /* Penukaran token setel ulang, dan penegasan sandi. Keduanya
           menerima rahasia yang dapat ditebak berulang-ulang. */
        RateLimiter::for('sandi', fn (Request $r) => [
            Limit::perMinute(6)->by('ip:'.$r->ip()),
            Limit::perMinute(6)->by('sesi:'.($r->user()?->getAuthIdentifier() ?? $r->ip())),
        ]);
    }
}
