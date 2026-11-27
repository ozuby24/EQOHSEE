<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Angka platform untuk panel halaman tamu (masuk, daftar, lupa sandi).
 *
 * Semuanya dihitung dari data sungguhan, tidak ada yang ditulis tetap:
 *  - modul & aspek   — registri Modules dan Pillars, berikut sebaran modul
 *                      per pilar utamanya untuk pita warna di bawah angkanya;
 *  - pengguna        — akun aktif;
 *  - pengunjung      — jumlah pengunjung harian anonim (CatatPengunjung),
 *                      berikut deret tujuh hari terakhir untuk grafik mini.
 *
 * Jumlah perusahaan pelanggan SENGAJA tidak ikut: properti halaman
 * terbaca siapa pun di sumber halaman masuk, dan banyaknya pelanggan
 * bukan angka untuk diumumkan kepada setiap pengunjung.
 *
 * Disimpan sepuluh menit: halaman masuk dibuka setiap orang setiap hari,
 * dan kueri hitung pada tiap pembukaannya membebani basis data demi angka
 * yang tidak perlu tepat sampai detik. Pengunjung baru menghapus simpanan
 * ini (CatatPengunjung) supaya hitungannya sendiri langsung terlihat.
 */
final class RingkasPlatform
{
    public const KUNCI_CACHE = 'ringkas-platform-tamu';

    /**
     * @return array{modul:int, aspek:int, pengguna:int, pengunjung:int, tren:list<int>,
     *               modulPerPilar:list<array{kunci:string, nama:string, jumlah:int}>}|null
     */
    public static function untukTamu(): ?array
    {
        /* Angka panel tidak boleh menggagalkan halaman masuk: basis data
           yang belum siap (pemasangan baru sebelum migrate) atau kueri yang
           gagal hanya menyembunyikan kacanya, halaman masuknya tetap ada. */
        try {
            return Cache::remember(self::KUNCI_CACHE, now()->addMinutes(10), fn () => self::hitung());
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{modul:int, aspek:int, pengguna:int, pengunjung:int, tren:list<int>,
     *               modulPerPilar:list<array{kunci:string, nama:string, jumlah:int}>}
     */
    public static function hitung(): array
    {
        [$pengunjung, $tren] = self::pengunjung();
        $modul = Modules::all();

        return [
            'modul'         => count($modul),
            'aspek'         => count(Pillars::all()),
            'pengguna'      => User::query()->where('active', true)->count(),
            'pengunjung'    => $pengunjung,
            'tren'          => $tren,
            'modulPerPilar' => self::modulPerPilar($modul),
        ];
    }

    /**
     * Banyaknya modul per pilar utamanya, terbanyak lebih dulu; pilar
     * tanpa modul tidak ikut. Jumlahnya selalu sama dengan jumlah modul.
     *
     * @param  list<array<string, mixed>>  $modul
     * @return list<array{kunci:string, nama:string, jumlah:int}>
     */
    private static function modulPerPilar(array $modul): array
    {
        $pilar = Pillars::all();
        $hitung = [];

        foreach ($modul as $m) {
            $k = (string) ($m['pilar'] ?? '');
            $hitung[$k] = ($hitung[$k] ?? 0) + 1;
        }

        // Terbanyak lebih dulu; seri diurutkan menurut urutan registri pilar
        // supaya urutannya tidak berubah-ubah di antara dua permintaan.
        $urutan = array_flip(array_keys($pilar));
        uksort($hitung, fn ($a, $b) => [$hitung[$b], $urutan[$a] ?? 99] <=> [$hitung[$a], $urutan[$b] ?? 99]);

        $hasil = [];
        foreach ($hitung as $k => $n) {
            $hasil[] = ['kunci' => $k, 'nama' => (string) ($pilar[$k]['nama'] ?? $k), 'jumlah' => $n];
        }

        return $hasil;
    }

    /** @return array{0:int, 1:list<int>} jumlah seluruhnya, dan tujuh hari terakhir (lama → baru) */
    private static function pengunjung(): array
    {
        if (!Schema::hasTable('kunjungan_harian')) {
            return [0, array_fill(0, 7, 0)];
        }

        $hariIni = Carbon::today();
        $awal = $hariIni->copy()->subDays(6);

        $perHari = DB::table('kunjungan_harian')
            ->whereBetween('tanggal', [$awal->toDateString(), $hariIni->toDateString()])
            ->pluck('pengunjung', 'tanggal');

        $tren = [];
        for ($i = 0; $i < 7; $i++) {
            $tgl = $awal->copy()->addDays($i)->toDateString();
            $tren[] = (int) ($perHari[$tgl] ?? 0);
        }

        return [(int) DB::table('kunjungan_harian')->sum('pengunjung'), $tren];
    }
}
