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
 * Masuk dan daftar dengan akun Google atau Microsoft.
 *
 * ── PENYEDIA MEMBUKTIKAN SIAPA ORANGNYA, BUKAN MEMBUAT AKUN ──
 *
 * Surel yang sudah punya akun EQOHSEE langsung masuk ke akun itu. Surel
 * yang belum punya akun TIDAK dibuatkan akun diam-diam: identitasnya
 * dititipkan di sesi (MasukPihakKetiga::titipkanIdentitas, berlaku
 * MENIT_DAFTAR menit) dan orangnya diantar ke formulir Daftar dengan
 * surel terkunci. Akunnya baru ada sesudah ia melengkapi jabatan dan
 * perusahaan demo lalu menekan Daftar sendiri — aturan pendaftaran yang
 * sama dengan pendaftaran bersandi (RegisteredUserController).
 *
 * "Masuk" dan "daftar" kembali ke alamat yang sama, karena hanya satu
 * alamat kembali yang didaftarkan di konsol penyedia; niatnya dititipkan
 * di sesi (KUNCI_NIAT) dan hanya menentukan ke halaman mana galatnya
 * dipulangkan.
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
    public function alihkan(Request $request, string $penyedia): Pengalihan
    {
        abort_unless(MasukPihakKetiga::aktif($penyedia), 404);

        // Niat daftar yang tertinggal dari percobaan sebelumnya dibuang.
        $request->session()->forget(MasukPihakKetiga::KUNCI_NIAT);

        return $this->kePenyedia($penyedia);
    }

    /** Tombol "Daftar dengan Google/Microsoft" di halaman Daftar. */
    public function daftar(Request $request, string $penyedia): Pengalihan
    {
        abort_unless(MasukPihakKetiga::aktif($penyedia), 404);

        $request->session()->put(MasukPihakKetiga::KUNCI_NIAT, 'daftar');

        return $this->kePenyedia($penyedia);
    }

    /**
     * "Pakai akun lain" / "Kembali ke halaman masuk": identitas yang
     * menunggu dibuang, lalu kembali ke formulir Daftar yang kosong atau
     * ke halaman masuk.
     */
    public function batalDaftar(Request $request): RedirectResponse
    {
        MasukPihakKetiga::lupakanIdentitas($request);

        return redirect()->route($request->input('ke') === 'masuk' ? 'login' : 'register');
    }

    public function kembali(Request $request, string $penyedia): RedirectResponse
    {
        abort_unless(MasukPihakKetiga::aktif($penyedia), 404);
        $nama = MasukPihakKetiga::nama($penyedia);
        $daftar = $request->session()->pull(MasukPihakKetiga::KUNCI_NIAT) === 'daftar';

        // Pengguna membatalkan di halaman penyedia.
        if ($request->filled('error')) {
            return $this->tolak($daftar, $daftar
                ? __('Daftar dengan :nama dibatalkan.', ['nama' => $nama])
                : __('Masuk dengan :nama dibatalkan.', ['nama' => $nama]));
        }

        try {
            $sosial = Socialite::driver($penyedia)->user();
        } catch (\Throwable $e) {
            Log::warning('Masuk pihak ketiga gagal', ['penyedia' => $penyedia, 'galat' => $e::class]);

            return $this->tolak($daftar, $daftar
                ? __('Daftar dengan :nama gagal. Coba lagi, atau isi formulir di bawah.', ['nama' => $nama])
                : __('Masuk dengan :nama gagal. Coba lagi, atau masuk dengan email dan kata sandi.', ['nama' => $nama]));
        }

        $surel = Str::lower(trim((string) $sosial->getEmail()));

        $terverifikasi = $penyedia === 'google'
            ? (bool) ($sosial->user['email_verified'] ?? $sosial->user['verified_email'] ?? false)
            : $surel !== '';   // PenyediaMicrosoft hanya mengembalikan surel berdomain terverifikasi

        if ($surel === '' || !$terverifikasi) {
            return $this->tolak($daftar, $daftar
                ? __('Surel akun :nama Anda belum terverifikasi oleh :nama, jadi tidak dapat dipakai untuk mendaftar.', ['nama' => $nama])
                : __('Surel akun :nama Anda belum terverifikasi oleh :nama, jadi tidak dapat dipakai untuk masuk.', ['nama' => $nama]));
        }

        $pengguna = User::where('email', $surel)->first();

        /* Belum ada akun: ke formulir Daftar dengan surel yang sudah
           terbukti, dari tombol Masuk maupun tombol Daftar. Formulirnya
           memberi tahu yang datang dari tombol Masuk bahwa akunnya belum
           ada — mungkin perusahaannya memberinya akun dengan surel kerja,
           dan yang dipilihnya tadi akun pribadi. */
        if ($pengguna === null) {
            MasukPihakKetiga::titipkanIdentitas($request, $penyedia, $surel, $sosial->getName(), dariMasuk: !$daftar);

            return redirect()->route('register');
        }

        if (!$pengguna->active) {
            return $this->tolak($daftar, __(PastikanAktif::PESAN));
        }

        // Identitas lama yang menunggu tidak lagi diperlukan.
        MasukPihakKetiga::lupakanIdentitas($request);

        if (DuaFaktor::menyala($pengguna)) {
            return DuaFaktorTantanganController::titipkan($request, $pengguna, false);
        }

        Auth::login($pengguna);
        $request->session()->regenerate(true);

        $tujuan = redirect()->intended(Lapangan::berandaUntuk($request));

        /* Yang menekan "Daftar dengan Google" padahal akunnya sudah ada
           diberi tahu kenapa ia tidak melihat formulir apa pun. */
        return $daftar
            ? $tujuan->with('sukses', __('Akun EQOHSEE untuk :surel sudah ada, jadi Anda langsung masuk.', ['surel' => $surel]))
            : $tujuan;
    }

    private function kePenyedia(string $penyedia): Pengalihan
    {
        // Selalu minta memilih akun: di komputer bersama, akun Google
        // yang terakhir dipakai belum tentu milik orang yang sekarang.
        return Socialite::driver($penyedia)->with(['prompt' => 'select_account'])->redirect();
    }

    private function tolak(bool $daftar, string $pesan): RedirectResponse
    {
        return redirect()->route($daftar ? 'register' : 'login')->with('galat', $pesan);
    }
}
