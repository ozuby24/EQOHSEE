<?php

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{Crypt, DB, Schema};
use Throwable;

/**
 * Pengaturan kunci → nilai yang diubah administrator dari antarmuka.
 *
 * Penyesuai untuk kode yang diporting dari Aurorasys (pengingat tindak
 * lanjut SMKP): Aurorasys menyimpannya di tabel `pengaturan(kunci, nilai)`;
 * EQOHSEE sudah punya tabel serupa — `app_settings(key, value)`, dipakai
 * Pusat Kendali dan sambungan AI — sehingga tidak dibuat tabel kedua.
 * Nama kuncinya berawalan modul (mis. `pengingat.aktif`), jadi tidak
 * bertabrakan dengan kunci yang sudah ada.
 *
 * Tidak disinggahkan: dibaca hanya oleh perintah terjadwal dan halaman
 * pengaturannya, dan singgahan yang tidak ikut dibuang oleh penulis lain
 * tabel ini (App\Support\Ai menulisnya langsung) lebih berbahaya daripada
 * satu kueri.
 *
 * Nilai rahasia disimpan terenkripsi APP_KEY lewat simpanRahasia()/
 * rahasia(); bila APP_KEY berganti, rahasia lama terbaca kosong — bukan
 * galat — dan perlu diisi ulang.
 */
class Pengaturan extends Model
{
    protected $table = 'app_settings';

    protected $fillable = ['key', 'value'];

    /** @return array<string,string|null> */
    public static function semua(): array
    {
        try {
            return Schema::hasTable('app_settings')
                ? DB::table('app_settings')->pluck('value', 'key')->all()
                : [];
        } catch (Throwable) {
            // Basis data belum siap (instalasi baru, migrasi tertunda): pakai bawaan.
            return [];
        }
    }

    public static function ambil(string $kunci, ?string $bawaan = null): ?string
    {
        try {
            $isi = DB::table('app_settings')->where('key', $kunci)->value('value');
        } catch (Throwable) {
            return $bawaan;
        }

        return $isi !== null ? (string) $isi : $bawaan;
    }

    public static function ada(string $kunci): bool
    {
        return DB::table('app_settings')->where('key', $kunci)->exists();
    }

    /** Simpan beberapa pengaturan sekaligus; null menghapus barisnya. @param array<string,string|null> $nilai */
    public static function simpan(array $nilai): void
    {
        foreach ($nilai as $kunci => $isi) {
            if ($isi === null) {
                DB::table('app_settings')->where('key', $kunci)->delete();
            } else {
                DB::table('app_settings')->updateOrInsert(
                    ['key' => $kunci],
                    ['value' => $isi, 'updated_at' => now(), 'created_at' => now()],
                );
            }
        }
    }

    public static function rahasia(string $kunci): ?string
    {
        $isi = self::ambil($kunci);
        if ($isi === null || $isi === '') return null;

        try {
            return Crypt::decryptString($isi);
        } catch (DecryptException) {
            return null;
        }
    }

    public static function simpanRahasia(string $kunci, ?string $isi): void
    {
        self::simpan([$kunci => $isi === null || $isi === '' ? null : Crypt::encryptString($isi)]);
    }
}
