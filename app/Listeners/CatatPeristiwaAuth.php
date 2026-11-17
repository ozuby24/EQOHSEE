<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\Keamanan;
use Illuminate\Auth\Events\{Failed, Lockout, Login, Logout, PasswordReset};

/**
 * Pencatat peristiwa masuk-keluar.
 *
 * Dipasang pada PERISTIWA, bukan pada controller. Alasannya bukan
 * kerapian: aplikasi ini punya lebih dari satu jalan masuk — borang
 * masuk biasa, pemulihan sandi, verifikasi kode — dan jalan-jalan itu
 * bertambah. Pencatat yang ditempelkan pada satu controller diam-diam
 * berhenti mencakup jalan berikutnya yang dibuat orang, dan yang
 * memeriksa jejaknya tidak punya cara mengetahui bahwa ada yang tidak
 * tercakup.
 *
 * Laravel sendiri yang melemparkan peristiwa ini, dari dalam guard-nya.
 * Selama masuknya lewat `Auth`, ia tercatat.
 */
class CatatPeristiwaAuth
{
    public function masuk(Login $e): void
    {
        $u = $e->user;

        Keamanan::catat(Keamanan::MASUK, null, $u instanceof User ? $u : null);

        if ($u instanceof User) {
            /* Ditulis tanpa menyentuh updated_at. Kolom itu dipakai
               di tempat lain untuk menandai perubahan data pengguna,
               dan masuk bukan perubahan data pengguna — menaikkannya
               tiap kali orang masuk membuat "terakhir diubah" pada
               daftar pengguna berhenti berarti apa pun. */
            User::withoutTimestamps(fn () => $u->forceFill([
                'masuk_terakhir_at' => now(),
                'masuk_terakhir_ip' => Keamanan::alamat(),
            ])->saveQuietly());
        }
    }

    /**
     * Percobaan yang ditolak.
     *
     * Yang disimpan sebagai `detail` adalah surel yang DICOBA, bukan
     * nama pengguna — pada percobaan yang gagal seringkali tidak ada
     * penggunanya, dan justru surel yang dicoba itulah keterangannya:
     * satu alamat yang mencoba dua puluh surel berbeda adalah
     * pemindaian, satu alamat yang mencoba satu surel dua puluh kali
     * adalah penebakan sandi. Keduanya perlu jawaban yang berbeda.
     *
     * Sandinya sendiri tidak pernah ikut. `$e->credentials` memuatnya,
     * dan hanya bagian surelnya yang diambil.
     */
    public function gagal(Failed $e): void
    {
        Keamanan::catat(
            Keamanan::MASUK_GAGAL,
            $this->surel($e->credentials),
            $e->user instanceof User ? $e->user : null,
        );
    }

    public function terkunci(Lockout $e): void
    {
        Keamanan::catat(
            Keamanan::TERKUNCI,
            $this->surel($e->request->only('email')),
        );
    }

    public function keluar(Logout $e): void
    {
        $u = $e->user;

        Keamanan::catat(Keamanan::KELUAR, null, $u instanceof User ? $u : null);
    }

    public function sandiDiatur(PasswordReset $e): void
    {
        $u = $e->user;

        Keamanan::catat(
            Keamanan::SANDI_BERUBAH,
            'lewat pemulihan sandi',
            $u instanceof User ? $u : null,
        );
    }

    /** @param  array<string,mixed>  $kredensial */
    private function surel(array $kredensial): ?string
    {
        $surel = $kredensial['email'] ?? null;

        return is_string($surel) && $surel !== '' ? mb_substr($surel, 0, 150) : null;
    }
}
