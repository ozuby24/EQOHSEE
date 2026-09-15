<?php

namespace App\Support;

/**
 * Warna tiap nada ubin dasbor.
 *
 * ── KENAPA TIGA NILAI, BUKAN SATU ──
 *
 * Satu warna per nada tampak cukup sampai warnanya dipakai di dua
 * tempat yang tuntutannya berlawanan.
 *
 * Ubin ikon menuntut warna yang PEKAT: ia bidang berwarna dengan
 * siluet putih di atasnya, dan kuning tua terbaca kusam di sana.
 * Angka di sebelahnya menuntut kebalikannya — ia tulisan di atas
 * kertas putih, dan kuning terang di sana tidak terbaca sama sekali.
 * Amber #F0A91B di atas putih hanya sekitar 2:1; teks tebal besar
 * butuh 3:1.
 *
 * Dipaksa memakai satu nilai, salah satunya selalu kalah, dan yang
 * kalah bukan yang terlihat jelek melainkan yang tidak terbaca —
 * biasanya angkanya, yang justru satu-satunya isi kartu itu.
 *
 * Jadi: `ubin` dan `terang` membentuk gradiennya, `teks` dipakai
 * tulisan dan bilah tepi kiri kartu.
 *
 * ── GRADIENNYA TIDAK TURUN MELEWATI WARNA DASAR ──
 *
 * `terang` selalu lebih terang daripada `ubin`, dan `ubin` adalah
 * ujung GELAP-nya. Menggelapkan lebih jauh dengan mencampur hitam
 * bukan menggelapkan melainkan memudarkan: merah jadi merah bata,
 * kuning jadi cokelat. Pasangan di bawah disetel tangan, bukan
 * dihitung, karena tiap warna berperilaku berbeda.
 */
class NadaWarna
{
    /**
     * @var array<string, array{ubin: string, terang: string, teks: string}>
     */
    public const PETA = [
        'gawat'  => ['ubin' => '#E11D2F', 'terang' => '#FF6A53', 'teks' => '#D31527'],
        'serius' => ['ubin' => '#F4701F', 'terang' => '#FFB15C', 'teks' => '#D65A0C'],
        'ingat'  => ['ubin' => '#F0A91B', 'terang' => '#FFD75E', 'teks' => '#A8730A'],
        'kabar'  => ['ubin' => '#0BA5E9', 'terang' => '#5FD0F7', 'teks' => '#0369A1'],
        'baik'   => ['ubin' => '#0F9D52', 'terang' => '#3FD382', 'teks' => '#0E8746'],
    ];

    /** Nada yang dipakai ketika ubinnya tidak menyebut nada apa pun. */
    public const BAWAAN = 'kabar';

    /**
     * @return array{ubin: string, terang: string, teks: string}
     */
    public static function untuk(?string $nada): array
    {
        return self::PETA[$nada] ?? self::PETA[self::BAWAAN];
    }
}
