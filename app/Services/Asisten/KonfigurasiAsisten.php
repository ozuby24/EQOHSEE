<?php

namespace App\Services\Asisten;

use App\Support\Ai;

/**
 * Konfigurasi asisten audit SMKP yang berlaku — penyesuai EQOHSEE.
 *
 * Aurorasys menyimpan penyedia, kunci, dan model asistennya sendiri
 * (Admin → Server → Asisten AI, tabel `pengaturan`). EQOHSEE sudah punya
 * satu sambungan AI untuk seluruh aplikasi — App\Support\Ai, diatur dari
 * Pusat Kendali → Asisten AI, kunci terenkripsi di `app_settings` — sehingga
 * di sini asisten audit MENUMPANG sambungan itu: tidak ada kunci kedua,
 * tidak ada paket SDK penyedia tambahan.
 *
 * config/asisten.php tetap ada untuk hal yang khusus asisten audit:
 * sakelar utama, batas per jam, batas token keluaran, dan tenggat.
 */
final class KonfigurasiAsisten
{
    /** Sakelar utama (belum memeriksa ketersediaan kunci). */
    public static function dinyalakan(): bool
    {
        return (bool) config('asisten.aktif', true);
    }

    /** Asisten dapat dipakai: dinyalakan dan penyedia aktif punya kunci. */
    public static function aktif(): bool
    {
        return self::dinyalakan() && Ai::aktif();
    }

    public static function penyedia(): string
    {
        return Ai::penyedia();
    }

    /** Nama model yang melayani — parameter penyedia diterima demi kecocokan dengan kode Aurorasys. */
    public static function model(?string $penyedia = null): string
    {
        return Ai::model();
    }

    /** Klien yang memanggil App\Support\Ai. */
    public static function klien(): KlienAi
    {
        return new KlienEqohsee(
            maksToken: max(1000, (int) config('asisten.maks_token', 16000)),
            jeda: max(10, (int) config('asisten.timeout', 100)),
            ulang: max(0, (int) config('asisten.ulang', 2)),
        );
    }
}
