<?php

namespace App\Providers;

use App\Listeners\CatatPeristiwaAuth;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\{Failed, Lockout, Login, Logout, PasswordReset};
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\{Event, Gate};
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
    }
}
