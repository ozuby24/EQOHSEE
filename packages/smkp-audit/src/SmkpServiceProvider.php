<?php

namespace Eqohsee\SmkpAudit;

use Eqohsee\SmkpAudit\Adapters\JejakDiam;
use Eqohsee\SmkpAudit\Adapters\PerusahaanDariPengguna;
use Eqohsee\SmkpAudit\Contracts\BatasPerusahaan;
use Eqohsee\SmkpAudit\Contracts\PencatatJejak;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Titik pasang modul Audit SMKP pada aplikasi Laravel.
 *
 * Yang didaftarkan hanya empat hal: setelan, dua titik sambung ke aplikasi
 * induk (batas perusahaan dan jejak aktivitas), migrasi, dan rute. Tampilan
 * Vue-nya TIDAK dimuat dari paket melainkan diterbitkan ke resources/js
 * aplikasi induk, sebab berkas itu dikompilasi Vite milik aplikasi — bukan
 * milik paket.
 */
class SmkpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/smkp.php', 'smkp');

        // Diikat lewat wadah, bukan dipanggil langsung, supaya aplikasi induk
        // dapat menukar keduanya tanpa menyunting satu baris pun modul.
        $this->app->bind(BatasPerusahaan::class, fn () => $this->app->make(
            config('smkp.perusahaan.penyaring') ?: PerusahaanDariPengguna::class
        ));

        $this->app->bind(PencatatJejak::class, fn () => $this->app->make(
            config('smkp.jejak') ?: JejakDiam::class
        ));
    }

    public function boot(): void
    {
        if (config('smkp.migrasi', true)) {
            $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        }

        if (config('smkp.rute.daftar', true)) {
            $this->rute();
        }

        if ($this->app->runningInConsole()) {
            $this->terbitan();
        }
    }

    /** Rute modul, dibungkus awalan dan middleware dari setelan. */
    private function rute(): void
    {
        Route::prefix(config('smkp.rute.awalan', 'smkp'))
            ->name(config('smkp.rute.nama', 'smkp.'))
            ->middleware(config('smkp.rute.middleware', ['web']))
            ->group(__DIR__.'/../routes/smkp.php');
    }

    /**
     * Berkas yang perlu disalin ke aplikasi induk.
     *
     * Vue dan CSS wajib diterbitkan — Vite hanya memindai resources/js
     * aplikasi. Config, acuan, dan migrasi hanya bila ingin disunting.
     */
    private function terbitan(): void
    {
        $this->publishes([
            __DIR__.'/../config/smkp.php' => config_path('smkp.php'),
        ], 'smkp-config');

        $this->publishes([
            __DIR__.'/../resources/js/Pages/Smkp'       => resource_path('js/Pages/Smkp'),
            __DIR__.'/../resources/js/Pages/Print'      => resource_path('js/Pages/Print'),
            __DIR__.'/../resources/js/Components'       => resource_path('js/Components'),
            __DIR__.'/../resources/js/Layouts'          => resource_path('js/Layouts'),
            __DIR__.'/../resources/css/smkp.css'        => resource_path('css/smkp.css'),
        ], 'smkp-vue');

        $this->publishes([
            __DIR__.'/../resources/data/elemen.json' => resource_path('data/smkp/elemen.json'),
        ], 'smkp-data');

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'smkp-migrations');
    }
}
