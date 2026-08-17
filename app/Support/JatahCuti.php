<?php

namespace App\Support;

use App\Models\MinersCuti;
use App\Models\MinersCutiJatah;
use App\Models\Paspor;

/**
 * Menghitung sisa cuti seseorang untuk satu tahun.
 *
 * Ditulis di satu tempat karena angkanya dipakai di tiga tempat yang
 * berbeda — layar pengajuan, layar rincian orang, dan penjaga yang
 * menolak pengajuan melebihi jatah — dan tiga salinan rumus sisa cuti
 * adalah tiga angka yang cepat atau lambat berselisih. Yang berselisih
 * di sini bukan tampilan: orang akan diberi tahu sisanya empat, lalu
 * ditolak saat mengambil tiga.
 *
 * YANG MASIH MENUNGGU IKUT DIHITUNG. Bila hanya yang disetujui yang
 * memotong, seseorang dapat mengajukan lima cuti sekaligus yang
 * masing-masing tampak muat dalam sisa jatahnya, lalu kelimanya
 * disetujui satu per satu dan jatahnya minus tanpa satu pun langkah
 * yang keliru. Karena itu sisanya dipisah menjadi dua angka —
 * "terpakai" dan "tertahan" — supaya yang membaca tahu mana yang sudah
 * pasti dan mana yang masih dapat batal.
 */
final class JatahCuti
{
    /**
     * @return array{
     *   tahun:int, jatah:int, bawaan:int, total:int,
     *   terpakai:int, tertahan:int, sisa:int, diatur:bool
     * }
     */
    public static function hitung(Paspor $p, ?int $tahun = null): array
    {
        $tahun ??= (int) now()->year;

        $baris = MinersCutiJatah::where('paspor_id', $p->getKey())
            ->where('tahun', $tahun)->first();

        $jatah  = $baris?->jatah  ?? MinersCutiJatah::BAKU;
        $bawaan = $baris?->bawaan ?? 0;

        $cuti = MinersCuti::where('paspor_id', $p->getKey())
            ->where('tahun', $tahun)->membebaniJatah()->get();

        $terpakai = (int) $cuti->where('status', Alur::DISETUJUI)->sum('jumlah_hari');
        $tertahan = (int) $cuti->where('status', Alur::DIAJUKAN)->sum('jumlah_hari');

        return [
            'tahun'    => $tahun,
            'jatah'    => $jatah,
            'bawaan'   => $bawaan,
            'total'    => $jatah + $bawaan,
            'terpakai' => $terpakai,
            'tertahan' => $tertahan,
            'sisa'     => $jatah + $bawaan - $terpakai - $tertahan,

            /* Apakah jatahnya memang pernah diatur, atau ini angka baku.
               Bedanya penting di layar: "12 hari" yang belum pernah
               ditetapkan siapa pun tidak boleh terlihat sama dengan
               "12 hari" yang sudah disepakati HRD. */
            'diatur'   => $baris !== null,
        ];
    }
}
