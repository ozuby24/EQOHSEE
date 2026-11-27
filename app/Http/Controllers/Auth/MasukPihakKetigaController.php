<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\PastikanAktif;
use App\Models\User;
use App\Support\{DuaFaktor, Lapangan, MasukPihakKetiga};
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as Pengalihan;

/**
 * Masuk dengan akun Google atau Microsoft.
 *
 * ── HANYA UNTUK AKUN YANG SUDAH ADA ──
 *
 * Akun EQOHSEE diberikan admin perusahaan (atau lewat pendaftaran yang
 * menempatkannya di perusahaan demo). Masuk dengan Google/Microsoft
 * TIDAK membuat akun baru: penyedia hanya membuktikan siapa orangnya,
 * lalu akun dengan surel yang sama dicari. Tidak ada, ditolak.
 *
 * ── ATURAN YANG SAMA DENGAN MASUK BERSANDI ──
 *
 *  - Surel harus terverifikasi oleh penyedianya: Google `email_verified`,
 *    Microsoft hanya surel berdomain milik penyewa (PenyediaMicrosoft).
 *  - Akun nonaktif ditolak dengan pesan yang sama.
 *  - Akun berdua faktor tetap diminta kodenya: penyedia menggantikan
 *    sandinya, bukan faktor keduanya.
 *  - Masuknya membangkitkan peristiwa Login biasa, jadi tercatat di
 *    jejak akses dan memperbarui masuk_terakhir_at.
 */
class MasukPihakKetigaController extends Controller
{
    public function alihkan(string $penyedia): Pengalihan
    {
        abort_unless(MasukPihakKetiga::aktif($penyedia), 404);

        $alur = Socialite::driver($penyedia);

        // Selalu minta memilih akun: di komputer bersama, akun Google
        // yang terakhir dipakai belum tentu milik orang yang sekarang.
        return $alur->with(['prompt' => 'select_account'])->redirect();
    }

    public function kembali(Request $request, string $penyedia): RedirectResponse
    {
        abort_unless(MasukPihakKetiga::aktif($penyedia), 404);
        $nama = MasukPihakKetiga::nama($penyedia);

        // Pengguna membatalkan di halaman penyedia.
        if ($request->filled('error')) {
            return $this->tolak(__('Masuk dengan :nama dibatalkan.', ['nama' => $nama]));
        }

        try {
            $sosial = Socialite::driver($penyedia)->user();
        } catch (\Throwable $e) {
            Log::warning('Masuk pihak ketiga gagal', ['penyedia' => $penyedia, 'galat' => $e::class]);

            return $this->tolak(__('Masuk dengan :nama gagal. Coba lagi, atau masuk dengan email dan kata sandi.', ['nama' => $nama]));
        }

        $surel = Str::lower(trim((string) $sosial->getEmail()));

        $terverifikasi = $penyedia === 'google'
            ? (bool) ($sosial->user['email_verified'] ?? $sosial->user['verified_email'] ?? false)
            : $surel !== '';   // PenyediaMicrosoft hanya mengembalikan surel berdomain terverifikasi

        if ($surel === '' || !$terverifikasi) {
            return $this->tolak(__('Surel akun :nama Anda belum terverifikasi oleh :nama, jadi tidak dapat dipakai untuk masuk.', ['nama' => $nama]));
        }

        $pengguna = User::where('email', $surel)->first();

        if ($pengguna === null) {
            return $this->tolak(__('Belum ada akun EQOHSEE untuk :surel. Akun diberikan admin perusahaan Anda, atau daftar lebih dulu.', ['surel' => $surel]));
        }

        if (!$pengguna->active) {
            return $this->tolak(__(PastikanAktif::PESAN));
        }

        if (DuaFaktor::menyala($pengguna)) {
            return DuaFaktorTantanganController::titipkan($request, $pengguna, false);
        }

        Auth::login($pengguna);
        $request->session()->regenerate(true);

        return redirect()->intended(Lapangan::berandaUntuk($request));
    }

    private function tolak(string $pesan): RedirectResponse
    {
        return redirect()->route('login')->with('galat', $pesan);
    }
}
