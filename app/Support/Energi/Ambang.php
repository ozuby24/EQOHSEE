<?php

namespace App\Support\Energi;

use App\Models\Energi\Pengaturan;
use Illuminate\Support\Carbon;

/**
 * Status wajib manajemen energi menurut PP 33/2023 (FSD §6.8).
 *
 *   WAJIB        konsumsi tahun lalu ≥ ambang
 *   PERINGATAN   proyeksi tahun berjalan ≥ 90% ambang
 *   TIDAK_WAJIB  lainnya — manajemen energi sukarela tetap dianjurkan
 *
 * Ambangnya pengaturan perusahaan, bukan tetapan: kategorisasi tambang
 * batubara sebagai "industri" (4.000 TOE) atau "penyedia energi" (6.000
 * TOE) belum dipastikan [A-01].
 */
final class Ambang
{
    public static function status(?int $companyId, ?int $tahun = null, ?Carbon $kini = null): array
    {
        $kini ??= now();
        $tahun ??= $kini->year;
        $atur = Pengaturan::untuk($companyId);
        $ambang = $atur->ambang();

        $saring = $companyId !== null ? ['company_id' => $companyId] : [];

        $lalu = Inventaris::ringkas(Inventaris::hitung(($tahun - 1).'-01-01', ($tahun - 1).'-12-31', $saring)['baris'])['toe'];

        $awal = Carbon::create($tahun, 1, 1);
        $akhir = $tahun < $kini->year ? Carbon::create($tahun, 12, 31) : $kini->copy()->startOfDay();
        $ytd = Inventaris::ringkas(Inventaris::hitung($awal, $akhir, $saring)['baris'])['toe'];

        $hariBerjalan = max(1, (int) $awal->diffInDays($akhir) + 1);
        $hariTahun = $awal->isLeapYear() ? 366 : 365;
        $proyeksi = $ytd / $hariBerjalan * $hariTahun;

        $status = match (true) {
            $lalu >= $ambang            => 'WAJIB',
            $proyeksi >= 0.9 * $ambang  => 'PERINGATAN',
            default                     => 'TIDAK_WAJIB',
        };

        return [
            'tahun'        => $tahun,
            'toe_lalu'     => $lalu,
            'toe_ytd'      => $ytd,
            'toe_proyeksi' => $proyeksi,
            'ambang'       => $ambang,
            'kategori'     => $atur->kategori_pengguna,
            'kategori_label' => Pengaturan::KATEGORI[$atur->kategori_pengguna]['label'] ?? $atur->kategori_pengguna,
            'dasar'        => Pengaturan::KATEGORI[$atur->kategori_pengguna]['dasar'] ?? 'PP 33/2023',
            'status'       => $status,
            'label'        => ['WAJIB' => 'Wajib manajemen energi', 'PERINGATAN' => 'Mendekati ambang',
                               'TIDAK_WAJIB' => 'Belum wajib'][$status],
            'gj_setara_ambang' => $ambang * (RegistriFaktor::nilai('konversi', 'gj_per_toe', $kini) ?? 41.868),
        ];
    }

    /** Ambang tepat batas: ≥, bukan > (FSD §16.1 AmbangTest). */
    public static function wajib(float $toe, float $ambang): bool
    {
        return $toe >= $ambang;
    }
}
