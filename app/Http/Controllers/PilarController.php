<?php

namespace App\Http\Controllers;

use App\Support\{Modules, Pillars};
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class PilarController extends Controller
{
    public function index()
    {
        $modules = array_map(function (array $module): array {
            $route = $module['rute'] ?? null;

            return $module + [
                'url' => $route && Route::has($route) ? route($route) : null,
            ];
        }, Modules::all());

        return Inertia::render('Pilar', [
            'pilar' => Pillars::all(),
            'modul' => $modules,
        ]);
    }
}
