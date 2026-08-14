<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Mengakhiri sesi.
     *
     * Memulangkan Inertia::location, bukan redirect biasa. Tujuannya —
     * halaman depan — dirender Blade, bukan Inertia. Ketika keluar
     * ditekan dari halaman Vue, tombolnya melakukan kunjungan Inertia,
     * dan Inertia yang menerima HTML utuh TIDAK jatuh sendiri ke navigasi
     * peramban: ia menampilkan HTML itu mentah-mentah di dalam bingkai
     * galat, sehingga halaman depan tampak muncul sebagai jendela rusak
     * di atas halaman yang baru saja ditinggalkan.
     *
     * Inertia::location menjawab keduanya: bagi permintaan Inertia ia
     * memerintahkan navigasi peramban penuh, bagi permintaan biasa —
     * tombol keluar pada halaman Blade — ia tetap pengalihan 302 seperti
     * sebelumnya.
     */
    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return Inertia::location('/');
    }
}
