<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Hash;

/**
 * Verifikasi email dengan kode enam angka.
 *
 * Kode disimpan sebagai hash — enam angka hanya sejuta kemungkinan, dan
 * kode yang tersimpan apa adanya dapat dipakai siapa pun yang sempat
 * membaca basis data sebelum masa berlakunya habis.
 *
 * Tiga pengaman dipasang bersama, sebab masing-masing sendirian tidak
 * memadai: masa berlaku pendek, batas percobaan, dan jeda kirim ulang.
 * Tanpa batas percobaan, sejuta kemungkinan dapat ditebak jauh lebih
 * cepat daripada kodenya kedaluwarsa. Tanpa jeda kirim ulang, batas
 * percobaan dapat dilewati hanya dengan meminta kode baru terus-menerus.
 */
trait VerifikasiKode
{
    /** Menit sebelum kode kedaluwarsa. */
    public const KODE_BERLAKU = 15;

    /** Salah sebanyak ini membuat kodenya hangus; harus minta yang baru. */
    public const KODE_MAKS_SALAH = 5;

    /** Detik minimal antara dua permintaan kode. */
    public const KODE_JEDA_KIRIM = 60;

    /** Membuat kode baru dan memulangkannya untuk dikirim. */
    public function buatKodeVerifikasi(): string
    {
        $kode = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $this->forceFill([
            'kode_verifikasi'           => Hash::make($kode),
            'kode_verifikasi_at'        => now(),
            'kode_verifikasi_percobaan' => 0,
        ])->save();

        return $kode;
    }

    public function kodeVerifikasiKedaluwarsa(): bool
    {
        return !$this->kode_verifikasi_at
            || $this->kode_verifikasi_at->addMinutes(self::KODE_BERLAKU)->isPast();
    }

    public function kodeVerifikasiHangus(): bool
    {
        return $this->kode_verifikasi_percobaan >= self::KODE_MAKS_SALAH;
    }

    /** Detik yang tersisa sebelum boleh meminta kode lagi; 0 bila boleh. */
    public function jedaKirimUlang(): int
    {
        if (!$this->kode_verifikasi_at) return 0;

        $boleh = $this->kode_verifikasi_at->addSeconds(self::KODE_JEDA_KIRIM);

        return $boleh->isFuture() ? (int) ceil(now()->diffInSeconds($boleh)) : 0;
    }

    /**
     * Memeriksa kode; bila cocok, emailnya ditandai terverifikasi.
     *
     * Percobaan yang gagal dicatat lebih dulu, sehingga penebakan tetap
     * terhitung meski permintaannya terputus setelah pemeriksaan.
     */
    public function periksaKodeVerifikasi(string $kode): bool
    {
        if (!$this->kode_verifikasi || $this->kodeVerifikasiKedaluwarsa() || $this->kodeVerifikasiHangus()) {
            return false;
        }

        if (!Hash::check($kode, $this->kode_verifikasi)) {
            $this->increment('kode_verifikasi_percobaan');

            return false;
        }

        $this->forceFill([
            'email_verified_at'         => now(),
            'kode_verifikasi'           => null,
            'kode_verifikasi_at'        => null,
            'kode_verifikasi_percobaan' => 0,
        ])->save();

        return true;
    }
}
