<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\KodeVerifikasiEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Verifikasi email dengan kode enam angka.
 *
 * Dipisahkan dari alur tautan bawaan Laravel. Tautan verifikasi hanya
 * bekerja bila surelnya dibuka di peramban yang sama dengan tempat
 * pendaftaran — di lapangan, surel kerja sering dibuka di ponsel lain
 * atau di aplikasi yang membuka tautan pada peramban dalamannya sendiri,
 * dan sesi di sana kosong. Kode yang diketik tidak punya masalah itu.
 */
class VerifikasiKodeController extends Controller
{
    /**
     * Kembali ke dashboard lewat navigasi peramban penuh.
     *
     * Dashboard dirender Blade, bukan Inertia. Pengalihan biasa dari
     * halaman verifikasi — yang Inertia — membuat Inertia menerima HTML
     * utuh, dan ia TIDAK jatuh sendiri ke navigasi peramban: HTML itu
     * ditampilkan mentah di dalam bingkai galat, sehingga dashboard tampak
     * sebagai jendela kosong di atas halaman verifikasi. Persis cacat yang
     * sama pernah terjadi pada tombol keluar.
     */
    private function keDashboard(): \Symfony\Component\HttpFoundation\Response
    {
        return redirect()->route('dashboard');
    }

    public function tampil(Request $r)
    {
        $u = $r->user();

        if ($u->hasVerifiedEmail()) return $this->keDashboard();

        // Datang ke halaman ini tanpa kode berlaku — mis. setelah kodenya
        // kedaluwarsa — tidak boleh menemui kotak isian yang tidak mungkin
        // diisi benar. Kode baru dibuatkan diam-diam.
        $gagalKirim = false;

        if ($u->kodeVerifikasiKedaluwarsa() && !$u->jedaKirimUlang()) {
            $gagalKirim = !$this->kirim($u);
        }

        return Inertia::render('Auth/Verifikasi', [
            'judul'    => 'Verifikasi Email',
            'subjudul' => 'Masukkan kode yang kami kirim ke surel Anda',

            'email'     => $u->email,
            'jeda'      => $u->jedaKirimUlang(),
            'hangus'    => $u->kodeVerifikasiHangus(),
            'berlaku'   => User::KODE_BERLAKU,

            // Ketika pengantar surelnya 'log', kodenya hanya ditulis ke
            // berkas log dan tidak pernah dikirim ke mana pun. Halaman
            // ini tetap mengatakan "kami mengirim kode" dan orangnya
            // menunggu surel yang tidak akan pernah datang. Lebih baik
            // dikatakan apa adanya beserta jalan keluarnya.
            'suratAktif' => $this->suratAktif() && !$gagalKirim,
        ]);
    }

    public function periksa(Request $r)
    {
        $d = $r->validate(
            ['kode' => ['required', 'digits:6']],
            ['kode.digits' => 'Kode terdiri dari 6 angka.'],
            ['kode' => 'kode verifikasi']
        );

        $u = $r->user();

        if ($u->hasVerifiedEmail()) return $this->keDashboard();

        if ($u->kodeVerifikasiHangus()) {
            throw ValidationException::withMessages([
                'kode' => 'Terlalu banyak percobaan. Minta kode baru.',
            ]);
        }

        if (!$u->periksaKodeVerifikasi($d['kode'])) {
            throw ValidationException::withMessages([
                'kode' => $u->kodeVerifikasiKedaluwarsa()
                    ? 'Kode sudah kedaluwarsa. Minta kode baru.'
                    : 'Kode tidak cocok.',
            ]);
        }

        session()->flash('sukses', 'Email Anda terverifikasi.');

        return $this->keDashboard();
    }

    public function kirimUlang(Request $r)
    {
        $u = $r->user();

        if ($u->hasVerifiedEmail()) return $this->keDashboard();

        /* Jeda dijaga di server, bukan hanya dengan menonaktifkan tombol.
           Tombol yang mati hanya menghalangi orang yang memakai halaman
           ini apa adanya; permintaannya sendiri tetap dapat diulang. */
        if ($detik = $u->jedaKirimUlang()) {
            throw ValidationException::withMessages([
                'kode' => "Tunggu {$detik} detik sebelum meminta kode baru.",
            ]);
        }

        $terkirim = $this->kirim($u);

        if (!$terkirim) {
            throw ValidationException::withMessages([
                'kode' => 'Kode baru dibuat, tetapi surelnya gagal dikirim. Hubungi administrator.',
            ]);
        }

        return back()->with('sukses', $this->suratAktif()
            ? 'Kode baru sudah dikirim.'
            : 'Kode baru dibuat, tetapi pengiriman surel belum aktif di server ini.');
    }

    /**
     * Kirim kode baru. Mengembalikan false bila pengirimannya gagal.
     *
     * Kegagalan SMTP — sandi salah, porta tertutup, penyedia menolak —
     * tidak boleh menjatuhkan halaman ini menjadi 500. Halaman inilah
     * satu-satunya jalan keluar orang yang belum terverifikasi; ia harus
     * tetap terbuka justru ketika pengiriman surelnya bermasalah.
     *
     * Isi galatnya dicatat di server saja. Pesan penyedia surel kerap
     * memuat nama pengguna dan petunjuk konfigurasi, dan itu bukan milik
     * siapa pun yang kebetulan membuka halaman pendaftaran.
     */
    private function kirim(User $u): bool
    {
        $kode = $u->buatKodeVerifikasi();

        try {
            $u->notify(new KodeVerifikasiEmail($kode));

            return true;
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim kode verifikasi', [
                'user_id' => $u->id,
                'galat'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** Server ini benar-benar mengirim surel, bukan sekadar mencatatnya. */
    private function suratAktif(): bool
    {
        return !in_array(config('mail.default'), ['log', 'array', 'null'], true);
    }
}
