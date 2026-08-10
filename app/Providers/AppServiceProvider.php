<?php

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
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
    }
}
