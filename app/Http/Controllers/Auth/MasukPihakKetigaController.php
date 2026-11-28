<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\PastikanAktif;
use App\Models\User;
use App\Support\{DuaFaktor, Lapangan, MasukPihakKetiga};
use GuzzleHttp\Exception\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
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
 * surel terkunci. Akunnya baru ada sesudah ia melengkapi data kerjanya
 * lalu menekan Daftar sendiri — aturan pendaftaran yang sama dengan
 * pendaftaran bersandi (RegisteredUserController).
 *
 * "Masuk" dan "daftar" kembali ke alamat yang sama, karena hanya satu
 * alamat kembali yang didaftarkan di konsol penyedia; niatnya dititipkan
 * di sesi (KUNCI_NIAT) dan hanya menentukan ke halaman mana galatnya
 * dipulangkan. Alamat kembali yang dilayani: /masuk/{penyedia}/kembali
 * (bawaan) dan /auth/{penyedia}/callback (alamat lazim panduan Socialite).
 *
 * ── STATE OAUTH MILIK SENDIRI ──
 *
 * State bawaan Socialite hanya disimpan di sesi. Di produksi sesi pernah
 * kembali dari Google tanpa state, dan setiap percobaan berakhir
 * InvalidStateException. State kini dibuat di sini, dititipkan di sesi
 * DAN di kuki khusus (MasukPihakKetiga::KUKI_STATE), dicocokkan sendiri,
 * lalu Socialite dipakai stateless hanya untuk menukar kode. Perlindungan
 * CSRF-nya tetap: state acak sekali pakai yang terikat ke peramban yang
 * memulai alurnya.
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
    /** Kode galat penyedia yang berarti pemasangannya salah, bukan orangnya. */
    private const GALAT_PEMASANGAN = ['invalid_client', 'unauthorized_client', 'redirect_uri_mismatch', 'invalid_request'];

    public function alihkan(Request $request, string $penyedia): Pengalihan
    {
        abort_unless(MasukPihakKetiga::aktif($penyedia), 404);

        if ($pindah = MasukPihakKetiga::pindahKeHostKanonik($request)) {
            return redirect()->away($pindah);
        }

        // Niat daftar yang tertinggal dari percobaan sebelumnya dibuang.
        $request->session()->forget(MasukPihakKetiga::KUNCI_NIAT);

        return $this->kePenyedia($request, $penyedia);
    }

    /** Tombol "Daftar dengan Google/Microsoft" di halaman Daftar. */
    public function daftar(Request $request, string $penyedia): Pengalihan
    {
        abort_unless(MasukPihakKetiga::aktif($penyedia), 404);

        if ($pindah = MasukPihakKetiga::pindahKeHostKanonik($request)) {
            return redirect()->away($pindah);
        }

        $request->session()->put(MasukPihakKetiga::KUNCI_NIAT, 'daftar');

        return $this->kePenyedia($request, $penyedia);
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

        // State titipan saat berangkat — sekali pakai, dari sesi atau kuki.
        $titipanSesi = $request->session()->pull(MasukPihakKetiga::KUNCI_STATE);
        $titipanKuki = $request->cookie(MasukPihakKetiga::KUKI_STATE);
        Cookie::queue(Cookie::forget(MasukPihakKetiga::KUKI_STATE));

        // Pengguna membatalkan di halaman penyedia.
        if ($request->filled('error')) {
            return $this->tolak($daftar, $daftar
                ? __('Daftar dengan :nama dibatalkan.', ['nama' => $nama])
                : __('Masuk dengan :nama dibatalkan.', ['nama' => $nama]));
        }

        $state = $request->query('state');
        $cocok = is_string($state) && $state !== '' && collect([$titipanSesi, $titipanKuki])
            ->contains(fn ($titipan) => is_string($titipan) && $titipan !== '' && hash_equals($titipan, $state));

        if (!$cocok) {
            Log::warning('Masuk pihak ketiga: state tidak cocok', [
                'penyedia'          => $penyedia,
                'state_di_sesi'     => is_string($titipanSesi),
                'state_di_kuki'     => is_string($titipanKuki),
                'state_di_alamat'   => is_string($state) && $state !== '',
                'host'              => $request->getHost(),
            ]);

            return $this->tolak($daftar, $daftar
                ? __('Waktu daftar dengan :nama habis atau terputus. Muat ulang halaman ini, lalu tekan tombol :nama sekali lagi.', ['nama' => $nama])
                : __('Waktu masuk dengan :nama habis atau terputus. Muat ulang halaman ini, lalu tekan tombol :nama sekali lagi.', ['nama' => $nama]));
        }

        try {
            // State sudah dicocokkan di atas; Socialite hanya menukar kodenya,
            // dengan alamat kembali yang sama persis dengan saat berangkat.
            $sosial = Socialite::driver($penyedia)->stateless()
                ->redirectUrl(MasukPihakKetiga::alamatKembali($penyedia))
                ->user();
        } catch (\Throwable $e) {
            $kode = $this->kodeGalatPenyedia($e);
            Log::warning('Masuk pihak ketiga gagal', [
                'penyedia' => $penyedia,
                'galat'    => $e::class,
                'kode'     => $kode,
                'pesan'    => Str::limit($e->getMessage(), 400),
            ]);

            if (in_array($kode, self::GALAT_PEMASANGAN, true)) {
                return $this->tolak($daftar, __('Masuk dengan :nama belum disetel dengan benar di server ini (:kode). Hubungi administrator.', ['nama' => $nama, 'kode' => $kode]));
            }

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

    /**
     * Berangkat ke penyedia: state acak dititipkan di sesi dan di kuki
     * khusus, alamat kembali selalu alamat lengkap dari APP_URL, dan
     * pilihan akun selalu diminta — di komputer bersama, akun Google yang
     * terakhir dipakai belum tentu milik orang yang sekarang.
     */
    private function kePenyedia(Request $request, string $penyedia): Pengalihan
    {
        $state = Str::random(40);
        $request->session()->put(MasukPihakKetiga::KUNCI_STATE, $state);
        Cookie::queue(MasukPihakKetiga::KUKI_STATE, $state, MasukPihakKetiga::MENIT_STATE);

        return Socialite::driver($penyedia)->stateless()
            ->redirectUrl(MasukPihakKetiga::alamatKembali($penyedia))
            ->with(['prompt' => 'select_account', 'state' => $state])
            ->redirect();
    }

    /**
     * Kode galat OAuth dari jawaban penyedia saat menukar kode (mis.
     * invalid_client: Client ID/Secret salah; redirect_uri_mismatch;
     * invalid_grant: kode kedaluwarsa atau sudah dipakai), atau null.
     */
    private function kodeGalatPenyedia(\Throwable $e): ?string
    {
        if (!$e instanceof RequestException || !$e->hasResponse()) {
            return null;
        }

        $isi = json_decode((string) $e->getResponse()->getBody(), true);
        $kode = is_array($isi) ? ($isi['error'] ?? null) : null;

        return is_string($kode) && preg_match('/^[a-z_]{3,40}$/', $kode) ? $kode : null;
    }

    private function tolak(bool $daftar, string $pesan): RedirectResponse
    {
        return redirect()->route($daftar ? 'register' : 'login')->with('galat', $pesan);
    }
}
