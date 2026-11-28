<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Masuk dan daftar dengan akun Google / Microsoft: penyedia mana yang
 * benar-benar dapat dipakai di pemasangan ini, dan identitas terverifikasi
 * yang menunggu formulir Daftar diselesaikan.
 *
 * Penyedia dianggap ada hanya bila client id DAN client secret-nya terisi
 * di .env. Halaman masuk dan daftar menggambar tombolnya dari daftar ini,
 * jadi tidak pernah ada tombol yang mengarah ke alur yang belum dipasang.
 */
final class MasukPihakKetiga
{
    public const PENYEDIA = ['google', 'microsoft'];

    /**
     * Niat alur yang sedang berjalan, dititipkan di sesi sebelum orangnya
     * dikirim ke penyedia. Penyedia hanya mengenal SATU alamat kembali per
     * aplikasi (/masuk/{penyedia}/kembali, yang didaftarkan di konsolnya),
     * jadi "masuk" dan "daftar" kembali ke alamat yang sama; niat inilah
     * yang membedakan ke halaman mana galatnya dipulangkan.
     */
    public const KUNCI_NIAT = 'sso.niat';

    /** Identitas terverifikasi yang menunggu formulir Daftar diselesaikan. */
    public const KUNCI_DAFTAR = 'sso.daftar';

    /**
     * Lama identitas itu berlaku. Pendek dengan sengaja: di komputer
     * bersama, identitas yang ditinggalkan di tengah jalan tidak boleh
     * dapat dipakai orang berikutnya untuk mendaftar atas nama surel itu.
     */
    public const MENIT_DAFTAR = 15;

    /** @return array{google: bool, microsoft: bool} */
    public static function tersedia(): array
    {
        $ada = [];
        foreach (self::PENYEDIA as $p) {
            $ada[$p] = self::aktif($p);
        }

        return $ada;
    }

    public static function aktif(string $penyedia): bool
    {
        if (!in_array($penyedia, self::PENYEDIA, true)) {
            return false;
        }

        return trim((string) config("services.{$penyedia}.client_id")) !== ''
            && trim((string) config("services.{$penyedia}.client_secret")) !== '';
    }

    public static function nama(string $penyedia): string
    {
        return $penyedia === 'microsoft' ? 'Microsoft' : 'Google';
    }

    /**
     * Simpan identitas yang baru dibuktikan penyedia, untuk formulir Daftar.
     * Akunnya BELUM dibuat: orangnya masih harus melengkapi jabatan dan
     * perusahaan demo lalu menekan Daftar sendiri.
     */
    public static function titipkanIdentitas(Request $request, string $penyedia, string $surel, ?string $nama, bool $dariMasuk): void
    {
        $request->session()->put(self::KUNCI_DAFTAR, [
            'penyedia'  => $penyedia,
            'email'     => $surel,
            'nama'      => trim((string) $nama),
            'dariMasuk' => $dariMasuk,
            'sampai'    => now()->addMinutes(self::MENIT_DAFTAR)->getTimestamp(),
        ]);
    }

    /**
     * Identitas yang menunggu, atau null bila tidak ada atau sudah lewat
     * waktunya (yang lewat waktu sekalian dibuang).
     *
     * @return array{penyedia: string, namaPenyedia: string, email: string, nama: string, dariMasuk: bool}|null
     */
    public static function identitas(Request $request): ?array
    {
        $isi = $request->session()->get(self::KUNCI_DAFTAR);

        if (!is_array($isi) || !self::aktif((string) ($isi['penyedia'] ?? ''))
            || (int) ($isi['sampai'] ?? 0) < now()->getTimestamp()) {
            if ($isi !== null) {
                self::lupakanIdentitas($request);
            }

            return null;
        }

        return [
            'penyedia'     => $isi['penyedia'],
            'namaPenyedia' => self::nama($isi['penyedia']),
            'email'        => (string) $isi['email'],
            'nama'         => (string) $isi['nama'],
            'dariMasuk'    => (bool) $isi['dariMasuk'],
        ];
    }

    public static function lupakanIdentitas(Request $request): void
    {
        $request->session()->forget(self::KUNCI_DAFTAR);
    }
}
