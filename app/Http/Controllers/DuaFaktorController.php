<?php

namespace App\Http\Controllers;

use App\Support\{DuaFaktor, Keamanan, Totp};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Menyalakan dan mematikan lapisan kedua, oleh pemilik akunnya sendiri.
 *
 * Sengaja TIDAK dibatasi administrator, dengan alasan yang sama seperti
 * "Perangkat saya": memasang pengaman pada akun sendiri adalah tindakan
 * yang tidak boleh perlu izin siapa pun.
 *
 * ── Sandi diminta lagi untuk mematikan ──
 *
 * Menyalakan tidak butuh sandi — yang menyalakan pengaman tidak
 * merugikan siapa pun. Mematikan butuh. Sesi yang tertinggal terbuka di
 * komputer bersama adalah cara paling murah untuk MEMBUANG lapisan
 * kedua sebuah akun, dan sesudahnya sandi yang sudah dipegang penyerang
 * kembali menjadi satu-satunya penghalang.
 */
class DuaFaktorController extends Controller
{
    public function index(Request $request)
    {
        $pengguna = $request->user();

        return Inertia::render('Akun/DuaFaktor', [
            'judul'    => 'Verifikasi Dua Langkah',
            'subjudul' => 'Lapisan kedua saat masuk, dari aplikasi autentikator di ponsel Anda',

            'menyala'    => DuaFaktor::menyala($pengguna),
            'aktifSejak' => $pengguna->dua_faktor_aktif_at?->translatedFormat('j F Y, H:i'),

            /* Rahasia dan QR hanya dikirim selama penyiapan BELUM
               disahkan. Sesudah menyala, tidak ada alasan rahasianya
               muncul lagi di peramban — dan setiap kemunculan adalah
               satu kesempatan lagi untuk terbaca dari layar yang sedang
               dibagikan. */
            'penyiapan' => DuaFaktor::menyala($pengguna) ? null : [
                'rahasia' => $pengguna->dua_faktor_rahasia,
                'uri'     => DuaFaktor::uriQr($pengguna),
            ],

            /* Kode pemulihan yang tersisa. Ditampilkan hanya bila
               diminta lewat tombolnya, supaya tidak ikut tergambar pada
               layar yang kebetulan sedang dilihat orang lain. */
            'sisaPemulihan' => count($pengguna->dua_faktor_pemulihan ?? []),
            'pemulihan'     => $request->session()->get('dua_faktor.pemulihan'),

            'jumlahPemulihan' => DuaFaktor::JUMLAH_PEMULIHAN,
            'angkaKode'       => Totp::ANGKA,
        ]);
    }

    /** Langkah pertama: menyiapkan rahasia dan menggambar QR-nya. */
    public function mulai(Request $request): RedirectResponse
    {
        $pengguna = $request->user();

        if (DuaFaktor::menyala($pengguna)) {
            return back()->withErrors(['kode' => 'Verifikasi dua langkah sudah menyala.']);
        }

        DuaFaktor::mulai($pengguna);

        return back();
    }

    /** Langkah kedua: membuktikan aplikasinya sudah memasang rahasianya. */
    public function sahkan(Request $request): RedirectResponse
    {
        $request->validate(['kode' => ['required', 'string', 'max:64']]);

        $pemulihan = DuaFaktor::sahkan($request->user(), (string) $request->input('kode'));

        if ($pemulihan === null) {
            throw ValidationException::withMessages([
                'kode' => 'Kodenya tidak cocok. Pastikan jam ponsel Anda benar, '
                    .'lalu masukkan kode yang sedang tampil.',
            ]);
        }

        Keamanan::catat(Keamanan::DF_NYALA);

        /* Lewat sesi sekali baca, bukan sebagai prop tetap: kode
           pemulihan harus muncul sekali sesudah dinyalakan, lalu hilang
           dari layar pada muat ulang berikutnya. */
        return back()->with('dua_faktor.pemulihan', $pemulihan);
    }

    public function matikan(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], [
            'password.current_password' => 'Kata sandinya tidak cocok.',
        ]);

        DuaFaktor::matikan($request->user());

        Keamanan::catat(Keamanan::DF_MATI);

        return back();
    }

    public function terbitkanUlang(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], [
            'password.current_password' => 'Kata sandinya tidak cocok.',
        ]);

        if (! DuaFaktor::menyala($request->user())) {
            return back()->withErrors(['password' => 'Verifikasi dua langkah belum menyala.']);
        }

        $pemulihan = DuaFaktor::terbitkanUlangPemulihan($request->user());

        Keamanan::catat(Keamanan::DF_PEMULIHAN);

        return back()->with('dua_faktor.pemulihan', $pemulihan);
    }
}
