<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Menanyakan keberadaan indeks kepada basis data.
 *
 * Ada karena satu jebakan yang tidak terlihat sama sekali dari
 * kodenya. Ini TIDAK bekerja, meski setiap orang yang membacanya
 * mengira ia bekerja:
 *
 *     Schema::table($tabel, function (Blueprint $b) {
 *         try { $b->dropUnique($nama); } catch (\Throwable) {}
 *         $b->unique([...]);
 *     });
 *
 * `$b->dropUnique()` tidak menjalankan apa pun. Ia hanya MENCATAT
 * perintah ke dalam daftar, dan seluruh daftar itu baru dijalankan
 * sesudah closure-nya selesai — di luar jangkauan try/catch. Maka bila
 * indeksnya tidak ada, migrasinya tetap jatuh, dengan pengaman yang
 * tampak terpasang rapi tepat di atas baris yang menjatuhkannya.
 *
 * Kegagalannya berhenti di tengah migrasi: sebagian tabel sudah berubah,
 * sisanya belum, dan tidak ada satu pun keadaan yang tercatat sebagai
 * "sudah". Memulihkannya menuntut orang membaca migrasinya lalu menebak
 * sampai mana ia sempat berjalan.
 *
 * Karena itu keberadaannya ditanyakan lebih dulu — dan ditanyakan
 * kepada basis datanya, bukan disimpulkan dari kode migrasi mana yang
 * tercatat pernah berjalan.
 */
final class Indeks
{
    public static function ada(string $tabel, string $nama): bool
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => collect(DB::select('PRAGMA index_list("'.$tabel.'")'))
                            ->contains(fn ($i) => $i->name === $nama),

            'mysql', 'mariadb' => DB::select(
                            'SHOW INDEX FROM `'.$tabel.'` WHERE Key_name = ?', [$nama]) !== [],

            'pgsql' => DB::select(
                            'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
                            [$tabel, $nama]) !== [],

            /* Penggerak yang tidak dikenal menjawab "tidak ada", dan itu
               pilihan yang disengaja: menjawab "ada" akan membuat indeks
               diam-diam TIDAK dibuat, dan ketiadaan indeks tidak pernah
               memunculkan galat — hanya halaman yang perlahan melambat.
               Menjawab "tidak ada" paling buruk menghasilkan galat
               "indeks sudah ada", yang segera terlihat dan mudah
               dimengerti. */
            default => false,
        };
    }
}
