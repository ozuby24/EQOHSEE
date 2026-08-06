<?php

namespace App\Providers;

use App\Models\User;
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
    }
}
