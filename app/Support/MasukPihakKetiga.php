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
     * State OAuth (penangkal CSRF pada alamat kembali), milik aplikasi
     * sendiri — bukan state bawaan Socialite yang HANYA disimpan di sesi.
     * Dititipkan di dua tempat: sesi (KUNCI_STATE) dan kuki khusus berumur
     * pendek (KUKI_STATE). Yang cocok salah satunya cukup.
     *
     * Kenapa dua: di produksi sesi pernah kembali dari Google TANPA state
     * (log: has_state=false, has_cookie=true) dan setiap percobaan berakhir
     * InvalidStateException — sesi yang dibaca saat kembali bukan sesi
     * yang ditulis saat berangkat (kuki sesi ganda sesudah SESSION_DOMAIN
     * diubah, atau berangkat dari www dan kembali ke tanpa-www). Kuki
     * khusus ini bernama baru, jadi tidak pernah punya kembaran lama, dan
     * ikut domain sesi. Terenkripsi (EncryptCookies), HttpOnly, SameSite
     * Lax — terkirim pada pengalihan GET dari Google — dan sekali pakai.
     */
    public const KUNCI_STATE = 'sso.state';
    public const KUKI_STATE = 'eq_sso';
    public const MENIT_STATE = 10;

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

    /**
     * Alamat kembali (redirect URI) yang dikirim ke penyedia — dan yang
     * HARUS terdaftar persis di konsol Google/Microsoft.
     *
     * Selalu alamat lengkap. Alamat relatif dari .env (bawaan
     * /masuk/{penyedia}/kembali) dilengkapi dengan APP_URL, bukan dengan
     * host permintaan: di balik proksi permintaan bisa terbaca http://,
     * dan pengunjung bisa datang lewat www. — keduanya menghasilkan alamat
     * yang berbeda dari yang terdaftar (redirect_uri_mismatch). APP_URL
     * yang belum diisi (localhost) tidak dipakai.
     */
    public static function alamatKembali(string $penyedia): string
    {
        $alamat = trim((string) config("services.{$penyedia}.redirect"));
        if ($alamat === '') {
            $alamat = "/masuk/{$penyedia}/kembali";
        }

        if (preg_match('#^https?://#i', $alamat)) {
            return $alamat;
        }

        $akar = self::akar();

        return $akar === null ? url($alamat) : $akar.'/'.ltrim($alamat, '/');
    }

    /**
     * Seluruh alamat kembali yang dilayani aplikasi untuk penyedia ini:
     * bawaan (/masuk/{penyedia}/kembali) dan alamat lazim panduan Laravel
     * Socialite (/auth/{penyedia}/callback). Salah satunya — yang sama
     * dengan alamatKembali() — harus terdaftar di konsol penyedia.
     *
     * @return list<string>
     */
    public static function alamatDiterima(string $penyedia): array
    {
        $akar = self::akar() ?? rtrim(url('/'), '/');

        return ["{$akar}/masuk/{$penyedia}/kembali", "{$akar}/auth/{$penyedia}/callback"];
    }

    /**
     * Alamat yang sama di host APP_URL bila permintaan datang lewat varian
     * www-nya (atau sebaliknya); null bila tidak perlu pindah.
     *
     * Alur masuk dimulai di host yang sama dengan alamat kembalinya, supaya
     * kuki state dan kuki sesi yang dibuat saat berangkat ikut terkirim
     * saat kembali. Hanya varian www yang dipindahkan — host lain sama
     * sekali (APP_URL yang salah isi) dibiarkan.
     */
    public static function pindahKeHostKanonik(Request $request): ?string
    {
        $akar = self::akar();
        if ($akar === null) {
            return null;
        }

        $kanonik = strtolower((string) parse_url($akar, PHP_URL_HOST));
        $host = strtolower($request->getHost());
        $tanpaWww = fn (string $h): string => (string) preg_replace('/^www\./', '', $h);

        if ($host === $kanonik || $tanpaWww($host) !== $tanpaWww($kanonik)) {
            return null;
        }

        return $akar.$request->getRequestUri();
    }

    /** APP_URL tanpa garis miring penutup, atau null bila belum diisi (localhost). */
    private static function akar(): ?string
    {
        $akar = rtrim((string) config('app.url'), '/');
        $host = strtolower((string) parse_url($akar, PHP_URL_HOST));

        return $host === '' || in_array($host, ['localhost', '127.0.0.1', '::1'], true) ? null : $akar;
    }
}
