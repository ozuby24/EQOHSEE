<?php

namespace App\Http\Controllers;

use App\Support\Keamanan;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Perangkat saya — kendali sesi milik pengguna sendiri.
 *
 * Sengaja TIDAK dibatasi administrator. Orang yang menduga sandinya
 * bocor harus dapat memutus perangkat lain saat itu juga, tanpa
 * menunggu siapa pun; selama masa tunggu itu penyusupnya memegang sesi
 * yang hidup, dan setiap menit tunggu adalah menit yang diberikan
 * cuma-cuma.
 *
 * Yang ditampilkan hanya sesi milik penggunanya sendiri. Penyaringnya
 * ada di dua tempat — di sini dan di dalam Keamanan::putusSesi — sebab
 * penyaring yang hanya ada pada tampilan akan dilewati oleh permintaan
 * yang tidak datang dari tampilan itu.
 */
class PerangkatSayaController extends Controller
{
    public function index(Request $request)
    {
        $pengguna = $request->user();
        $sesiKini = $request->session()->getId();

        return Inertia::render('Akun/Perangkat', [
            'judul'    => 'Perangkat & Keamanan Akun',
            'subjudul' => 'Perangkat yang sedang masuk dengan akun Anda',

            'sesi'     => Keamanan::sesiAktif($pengguna, $sesiKini),
            'riwayat'  => Keamanan::riwayat(15, $pengguna),

            'masukTerakhir' => [
                'kapan' => $pengguna->masuk_terakhir_at?->diffForHumans(),
                'ip'    => $pengguna->masuk_terakhir_ip,
            ],

            'tautan' => [
                'putus'     => route('keamanan.perangkat.putus'),
                'putusLain' => route('keamanan.perangkat.putus-lain'),
            ],
        ]);
    }

    /** Putuskan satu perangkat — hanya bila sesinya memang milik sendiri. */
    public function putus(Request $request)
    {
        $data = $request->validate(['id' => ['required', 'string', 'max:255']]);

        if (hash_equals($data['id'], $request->session()->getId())) {
            return back()->withErrors(['keamanan' =>
                'Itu perangkat yang sedang Anda pakai. Gunakan tombol Keluar untuk mengakhirinya.']);
        }

        if (!Keamanan::putusSesi($data['id'], $request->user())) {
            return back()->withErrors(['keamanan' =>
                'Perangkat itu sudah tidak aktif — mungkin sesinya baru saja berakhir.']);
        }

        Keamanan::catat(Keamanan::SESI_DIPUTUS, 'satu perangkat diputus oleh pemiliknya');

        return back()->with('ok', 'Perangkat itu diputus.');
    }

    /**
     * Putuskan semua perangkat lain.
     *
     * Sesi yang sedang dipakai sengaja dipertahankan. Memutus semuanya
     * termasuk yang sekarang akan melempar orangnya ke halaman masuk
     * tepat ketika ia sedang menangani dugaan pembobolan — dan langkah
     * berikutnya yang harus ia lakukan, mengganti sandi, menuntut
     * dirinya tetap masuk.
     */
    public function putusLain(Request $request)
    {
        $n = Keamanan::putusSesiLain($request->user(), $request->session()->getId());

        Keamanan::catat(Keamanan::SESI_DIPUTUS, $n.' perangkat lain diputus oleh pemiliknya');

        return back()->with('ok', $n === 0
            ? 'Tidak ada perangkat lain yang sedang masuk.'
            : $n.' perangkat lain diputus. Semuanya harus masuk lagi.');
    }
}
