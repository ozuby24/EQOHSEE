<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Lapisan kedua saat masuk.
 *
 * Turnstile membuktikan "ini manusia". Aturan sandi membuat sandinya
 * mahal ditebak. Tidak satu pun dari keduanya menolong ketika sandinya
 * sudah benar-benar dipegang orang lain — dibaca dari catatan di meja,
 * dipakai ulang dari situs yang bocor, atau diserahkan sendiri lewat
 * halaman tiruan. Untuk semua itu yang menahan hanya lapisan kedua.
 *
 * ── Menyala dalam dua langkah, bukan satu ──
 *
 * mulai() menyimpan rahasianya tetapi TIDAK menyalakan apa pun.
 * Lapisan ini baru menagih kode sesudah sahkan() berhasil — yaitu
 * sesudah orangnya membuktikan aplikasinya benar-benar sudah memasang
 * rahasia yang sama.
 *
 * Satu langkah akan lebih ringkas dan akan mengunci orang di luar
 * secara rutin: memindai QR, menutup halaman karena ada panggilan
 * radio, lalu terkunci pada percobaan masuk berikutnya oleh fitur yang
 * ia sendiri tidak yakin sudah ia pasang.
 *
 * ── Jalan pulang ──
 *
 * Ponsel hilang, rusak, atau diganti adalah kejadian biasa, bukan
 * kejadian luar biasa. Karena itu jalan pulangnya dirancang lebih dulu
 * daripada penguncinya:
 *
 *   1. Kode pemulihan, delapan buah, ditampilkan saat menyalakan dan
 *      dapat dibaca lagi kapan saja dari sesi yang sudah masuk.
 *   2. Administrator dapat mematikan lapisan ini untuk akun mana pun —
 *      dan tindakan itu tercatat.
 *
 * Tanpa keduanya, fitur ini berhenti menjadi pengaman dan berubah
 * menjadi cara kehilangan akun.
 */
class DuaFaktor
{
    /** Banyak kode pemulihan yang diterbitkan sekali jalan. */
    public const JUMLAH_PEMULIHAN = 8;

    /** Nama yang muncul di aplikasi autentikator. */
    public const PENERBIT = 'EQOHSEE';

    public static function menyala(?User $pengguna): bool
    {
        return $pengguna?->dua_faktor_aktif_at !== null
            && $pengguna->dua_faktor_rahasia !== null;
    }

    /**
     * Langkah pertama: menyiapkan rahasia, tanpa menyalakan apa pun.
     *
     * Memulangkan rahasianya supaya halaman penyiapan dapat menggambar
     * QR-nya. Dipanggil ulang, ia MENGGANTI rahasia yang belum disahkan
     * — orang yang mengulang penyiapan karena QR pertamanya gagal
     * dipindai tidak boleh terikat pada rahasia yang tidak pernah
     * sampai ke ponselnya.
     */
    public static function mulai(User $pengguna): string
    {
        /* Yang sudah menyala tidak boleh disetel ulang diam-diam.
           Rahasia baru pada akun yang sedang memakai lapisan ini akan
           mematikan aplikasi autentikator yang sudah terpasang, tanpa
           satu pun peringatan di layar. */
        if (self::menyala($pengguna)) {
            throw new \LogicException(
                'Dua faktor sudah menyala; matikan dulu sebelum menyiapkan rahasia baru.'
            );
        }

        $rahasia = Totp::rahasiaBaru();

        $pengguna->forceFill([
            'dua_faktor_rahasia'  => $rahasia,
            'dua_faktor_aktif_at' => null,
            'dua_faktor_langkah'  => null,
        ])->save();

        return $rahasia;
    }

    /**
     * Langkah kedua: membuktikan aplikasinya sudah memasang rahasianya.
     *
     * Memulangkan kode pemulihan bila berhasil, null bila kodenya salah.
     *
     * @return array<int, string>|null
     */
    public static function sahkan(User $pengguna, string $kode): ?array
    {
        $rahasia = $pengguna->dua_faktor_rahasia;

        if ($rahasia === null || self::menyala($pengguna)) return null;

        $langkah = Totp::cocok($rahasia, $kode);

        if ($langkah === null) return null;

        $pemulihan = self::kodePemulihanBaru();

        $pengguna->forceFill([
            'dua_faktor_pemulihan' => $pemulihan,
            'dua_faktor_aktif_at'  => now(),
            'dua_faktor_langkah'   => $langkah,
        ])->save();

        return $pemulihan;
    }

    /**
     * Memeriksa kode saat masuk — dari aplikasi ATAU dari daftar
     * pemulihan.
     *
     * Keduanya diperiksa di satu tempat karena keduanya dipakai lewat
     * satu kolom yang sama di layar. Memisahkannya menjadi dua kolom
     * memaksa orang yang ponselnya baru saja hilang memilih kolom yang
     * benar lebih dulu, pada saat ia paling tidak tenang.
     */
    public static function periksa(User $pengguna, string $kode): bool
    {
        if (! self::menyala($pengguna)) return false;

        $langkah = Totp::cocok(
            (string) $pengguna->dua_faktor_rahasia,
            $kode,
            $pengguna->dua_faktor_langkah,
        );

        if ($langkah !== null) {
            $pengguna->forceFill(['dua_faktor_langkah' => $langkah])->save();

            return true;
        }

        return self::pakaiKodePemulihan($pengguna, $kode);
    }

    /**
     * Kode pemulihan sekali pakai.
     *
     * Yang terpakai DIBUANG dari daftar, bukan ditandai. Kode pemulihan
     * yang masih dapat dipakai dua kali bukan jalan pulang melainkan
     * sandi kedua yang tidak pernah kedaluwarsa.
     */
    private static function pakaiKodePemulihan(User $pengguna, string $kode): bool
    {
        $kode   = self::rapikan($kode);
        $daftar = $pengguna->dua_faktor_pemulihan ?? [];

        foreach ($daftar as $i => $tersimpan) {
            if (! hash_equals(self::rapikan((string) $tersimpan), $kode)) continue;

            unset($daftar[$i]);

            $pengguna->forceFill([
                'dua_faktor_pemulihan' => array_values($daftar),
            ])->save();

            /* Dicatat, selalu. Kode pemulihan yang terpakai berarti
               salah satu dari dua hal: ponsel pemiliknya hilang, atau
               daftar pemulihannya yang bocor. Keduanya perlu terlihat,
               dan yang kedua tidak akan pernah dilaporkan siapa pun. */
            Log::notice('Kode pemulihan dua faktor dipakai', [
                'pengguna' => $pengguna->getKey(),
                'sisa'     => count($daftar),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Mematikan lapisan ini dan membuang seluruh jejaknya.
     *
     * Rahasianya ikut dibuang, bukan disimpan "kalau-kalau dinyalakan
     * lagi". Rahasia yang tertinggal pada akun yang sudah mematikan
     * fiturnya adalah rahasia yang tidak dijaga siapa pun dan tidak
     * diketahui pemiliknya masih ada.
     */
    public static function matikan(User $pengguna): void
    {
        $pengguna->forceFill([
            'dua_faktor_rahasia'   => null,
            'dua_faktor_pemulihan' => null,
            'dua_faktor_aktif_at'  => null,
            'dua_faktor_langkah'   => null,
        ])->save();
    }

    /**
     * Menerbitkan ulang kode pemulihan.
     *
     * @return array<int, string>
     */
    public static function terbitkanUlangPemulihan(User $pengguna): array
    {
        $pemulihan = self::kodePemulihanBaru();

        $pengguna->forceFill(['dua_faktor_pemulihan' => $pemulihan])->save();

        return $pemulihan;
    }

    public static function uriQr(User $pengguna): ?string
    {
        $rahasia = $pengguna->dua_faktor_rahasia;

        return $rahasia === null
            ? null
            : Totp::uri($rahasia, (string) $pengguna->email, self::PENERBIT);
    }

    /**
     * @return array<int, string>
     */
    private static function kodePemulihanBaru(): array
    {
        return collect(range(1, self::JUMLAH_PEMULIHAN))
            ->map(fn () => Str::lower(Str::random(5)).'-'.Str::lower(Str::random(5)))
            ->all();
    }

    /** Huruf kecil tanpa tanda hubung — orang menyalinnya sesuka hati. */
    private static function rapikan(string $kode): string
    {
        return Str::lower(preg_replace('/[^a-zA-Z0-9]/', '', $kode) ?? '');
    }
}
