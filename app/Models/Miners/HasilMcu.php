<?php

namespace App\Models\Miners;

/**
 * Hasil pemeriksaan kesehatan beserta nilainya.
 *
 * `layak` TIDAK diturunkan dari `nilai`, dan itu pembedaan yang
 * menentukan: "Fit With Note" bernilai di bawah Fit penuh tetapi
 * orangnya tetap boleh bekerja. Diturunkan dari angka, hasil itu akan
 * menolak permit bagi orang yang sesungguhnya layak — penolakan yang
 * tidak menimbulkan galat dan hanya terlihat oleh yang ditolak.
 */
class HasilMcu extends Master
{
    protected $table = 'mnr_hasil_mcu';

    protected function casts(): array
    {
        return parent::casts() + ['nilai' => 'integer', 'layak' => 'boolean'];
    }

    /** @return array<int,string> hanya hasil yang membolehkan kartu terbit. */
    public static function pilihanLayak(): array
    {
        return static::query()->where('layak', true)->orderBy('urutan')->pluck('nama', 'id')->all();
    }
}
