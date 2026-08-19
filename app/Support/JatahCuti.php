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
        return self::hitungBanyak([$p], $tahun)[$p->getKey()];
    }

    /**
     * Sisa cuti SEKUMPULAN orang, dalam dua kueri — berapa pun jumlahnya.
     *
     * Rumusnya tidak digandakan: `hitung()` untuk satu orang memanggil
     * yang ini juga. Alasan yang sama dengan alasan kelas ini ada —
     * dua salinan rumus sisa cuti adalah dua angka yang cepat atau
     * lambat berselisih, dan yang berselisih bukan tampilan melainkan
     * jawaban yang diterima orang di layar lalu dibantah saat ia
     * mengajukan.
     *
     * Yang melahirkan versi ini: layar /miners/cuti menghitung saldo
     * untuk SETIAP orang, dan memanggil `hitung()` sekali per orang
     * berarti dua kueri per orang. Terukur pada empat puluh orang: 177
     * kueri untuk satu halaman, 160 di antaranya dua pola yang sama
     * berulang. Bertambah lurus mengikuti jumlah pegawai — yaitu
     * memburuk persis ketika perusahaannya bertumbuh.
     *
     * @param  iterable<Paspor>  $orang
     * @return array<int, array{
     *   tahun:int, jatah:int, bawaan:int, total:int,
     *   terpakai:int, tertahan:int, sisa:int, diatur:bool
     * }>  dikunci paspor_id
     */
    public static function hitungBanyak(iterable $orang, ?int $tahun = null): array
    {
        $tahun ??= (int) now()->year;

        $id = [];
        foreach ($orang as $p) { $id[] = $p->getKey(); }
        if (!$id) return [];

        $jatahPer = MinersCutiJatah::whereIn('paspor_id', $id)
            ->where('tahun', $tahun)->get()->keyBy('paspor_id');

        $cutiPer = MinersCuti::whereIn('paspor_id', $id)
            ->where('tahun', $tahun)->membebaniJatah()->get()->groupBy('paspor_id');

        $keluar = [];

        foreach ($id as $pid) {
            $baris = $jatahPer->get($pid);
            $cuti  = $cutiPer->get($pid) ?? collect();

            $jatah  = $baris?->jatah  ?? MinersCutiJatah::BAKU;
            $bawaan = $baris?->bawaan ?? 0;

            $terpakai = (int) $cuti->where('status', Alur::DISETUJUI)->sum('jumlah_hari');
            $tertahan = (int) $cuti->where('status', Alur::DIAJUKAN)->sum('jumlah_hari');

            $keluar[$pid] = [
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

        return $keluar;
    }
}
