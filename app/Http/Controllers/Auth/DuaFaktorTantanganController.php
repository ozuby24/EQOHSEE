<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\DuaFaktor;
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\{Auth, RateLimiter};
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Halaman kode, di antara sandi yang benar dan sesi yang hidup.
 *
 * ── Yang disimpan di sesi, dan kenapa sesedikit itu ──
 *
 * Hanya nomor akun, penanda "ingat saya", dan waktu mulainya. BUKAN
 * pengguna yang sudah masuk, dan bukan penanda apa pun yang memberi
 * akses ke sesuatu. Selama halaman ini terbuka, pemegang sesinya tidak
 * dapat membuka satu halaman pun di dalam aplikasi — ia hanya punya
 * catatan bahwa sandi sebuah akun sudah pernah benar di peramban ini.
 *
 * ── Kenapa ada batas waktunya ──
 *
 * Tanpa batas, sandi yang benar hari ini menjadi setengah-masuk yang
 * menganggur di sesi peramban sampai berminggu-minggu. Peramban di
 * komputer bersama — dan di site tambang komputer memang bersama —
 * menyimpannya sampai ada yang membukanya lagi, lalu cukup satu kode
 * untuk masuk sebagai orang yang sudah lama pulang.
 */
class DuaFaktorTantanganController extends Controller
{
    /** Kunci sesi tempat setengah-masuk itu disimpan. */
    public const KUNCI = 'dua_faktor.menunggu';

    /** Umur setengah-masuk, dalam detik. */
    public const UMUR = 300;

    /**
     * Tebakan yang diizinkan per akun per menit.
     *
     * Kodenya hanya enam angka — sejuta kemungkinan — dan satu kode
     * hidup sampai satu setengah menit. Penebak yang tidak dibatasi
     * menghabiskan sejuta tebakan jauh lebih cepat daripada itu, dan
     * seluruh lapisan kedua ini berhenti menahan apa pun.
     *
     * Lima masih longgar bagi orang yang salah ketik berkali-kali
     * dengan sarung tangan kerja, dan menyisakan peluang yang tidak
     * berarti bagi yang menebak sepanjang hari.
     */
    public const TEBAKAN_PER_MENIT = 5;

    /**
     * Menitipkan setengah-masuk, lalu mengarahkan ke halaman kode.
     */
    public static function titipkan(Request $request, User $pengguna, bool $ingat): RedirectResponse
    {
        /* Sesi diperbarui pengenalnya SEBELUM apa pun dititipkan.
           Peramban yang datang membawa pengenal sesi pilihan penyerang
           — ditanam lewat tautan atau lewat komputer bersama — akan
           membawa pengenal itu juga sesudah masuk, dan sesi yang
           dimasukinya adalah sesi yang pengenalnya sudah dipegang orang
           lain sejak awal. */
        $request->session()->regenerate(true);

        $request->session()->put(self::KUNCI, [
            'id'    => $pengguna->getKey(),
            'ingat' => $ingat,
            'pada'  => now()->timestamp,
        ]);

        return redirect()->route('dua-faktor.tantangan');
    }

    public function tampil(Request $request)
    {
        $menunggu = $this->menunggu($request);

        if ($menunggu === null) return redirect()->route('login');

        $pengguna = User::find($menunggu['id']);

        return Inertia::render('Auth/DuaFaktor', [
            /* Surelnya ditampilkan supaya orang tahu akun mana yang
               sedang ditanya — komputer bersama dipakai bergantian, dan
               halaman kode tanpa nama adalah halaman yang mudah diisi
               untuk akun yang salah. Hanya surelnya; tidak ada satu pun
               data lain dari akun itu yang perlu terbaca sebelum
               kodenya benar. */
            'surel' => $pengguna?->email,

            'sisaDetik' => max(0, self::UMUR - (now()->timestamp - $menunggu['pada'])),
        ]);
    }

    /**
     * @throws ValidationException
     */
    public function kirim(Request $request): RedirectResponse
    {
        $request->validate(['kode' => ['required', 'string', 'max:64']]);

        $menunggu = $this->menunggu($request);

        if ($menunggu === null) {
            throw ValidationException::withMessages([
                'kode' => 'Waktu pengisian kode sudah habis. Silakan masuk lagi dari awal.',
            ]);
        }

        /* Dibatasi DI SINI, bukan lewat middleware throttle.
         *
         * Kuncinya nomor akun yang sedang ditanya — satu-satunya kunci
         * yang benar, karena penebaknya sudah memegang sandinya dan
         * sudah memegang satu setengah-masuk. Kunci itu hanya ada
         * sesudah sesinya dimuat, dan middleware throttle berjalan
         * sebelum itu: di sana session()->getId() memulangkan pengenal
         * baru tiap permintaan, sehingga pembatasnya tidak pernah
         * menahan apa pun sambil tetap tampak terpasang. */
        $kunci = 'dua-faktor:'.$menunggu['id'];

        if (RateLimiter::tooManyAttempts($kunci, self::TEBAKAN_PER_MENIT)) {
            throw ValidationException::withMessages([
                'kode' => 'Terlalu banyak percobaan. Coba lagi dalam '
                    .RateLimiter::availableIn($kunci).' detik.',
            ]);
        }

        $pengguna = User::find($menunggu['id']);

        if ($pengguna === null || ! DuaFaktor::periksa($pengguna, (string) $request->input('kode'))) {
            RateLimiter::hit($kunci);

            throw ValidationException::withMessages([
                'kode' => 'Kodenya tidak cocok. Periksa lagi aplikasi autentikator Anda, '
                    .'atau pakai salah satu kode pemulihan.',
            ]);
        }

        RateLimiter::clear($kunci);

        $request->session()->forget(self::KUNCI);

        Auth::guard('web')->loginUsingId($menunggu['id'], $menunggu['ingat']);

        /* Diperbarui lagi sesudah masuk, dengan alasan yang sama seperti
           pada AuthenticatedSessionController: pengenal sesi yang dipakai
           sebagai tamu tidak diteruskan ke sesi yang sudah masuk. */
        $request->session()->regenerate(true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Membatalkan setengah-masuk dan kembali ke halaman masuk.
     *
     * Ada tombolnya di layar. Orang yang salah memasukkan akun di
     * komputer bersama harus bisa mundur tanpa menunggu lima menit, dan
     * tanpa menutup peramban yang mungkin bukan miliknya.
     */
    public function batal(Request $request): RedirectResponse
    {
        $request->session()->forget(self::KUNCI);

        return redirect()->route('login');
    }

    /**
     * @return array{id: mixed, ingat: bool, pada: int}|null
     */
    private function menunggu(Request $request): ?array
    {
        $menunggu = $request->session()->get(self::KUNCI);

        if (! is_array($menunggu) || ! isset($menunggu['id'], $menunggu['pada'])) {
            return null;
        }

        if (now()->timestamp - $menunggu['pada'] > self::UMUR) {
            $request->session()->forget(self::KUNCI);

            return null;
        }

        return $menunggu;
    }
}
