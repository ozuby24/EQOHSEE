<?php

namespace App\Support\Miners;

use App\Models\Miners\Alur;
use App\Support\Waktu;

/**
 * Label, alamat, dan langkah persetujuan dokumen Miners.
 *
 * Dulu kelas ini juga menyusun SELURUH prop halaman rincian bersama
 * (Miners/Dokumen.vue) untuk keempat jenis dokumen. Sejak tiap jenis
 * punya halaman rinciannya sendiri (McuRincian, InduksiRincian,
 * PermitRincian, SimperRincian, SimperLanjutanRincian,
 * PerpanjanganRincian), yang tersisa di sini hanyalah yang dipakai
 * bersama: label jenis, alamat rincian untuk surel dan antrean, dan
 * bentuk langkah persetujuan yang digambar stepper TabelPersetujuan.
 */
final class RincianDokumen
{
    /** Label jenis dokumen sebagaimana disebut orang, bukan nama tabel. */
    public const LABEL = [
        'mcu'     => 'MCU',
        'induksi' => 'Induksi',
        'permit'  => 'Mine Permit',
        'simper'  => 'SIMPER',
        'ajuan'   => 'SIMPER Lanjutan',
        'perpanjangan_permit' => 'Perpanjangan Mine Permit',
    ];

    /** Alamat daftar tiap jenis, untuk tautan "kembali". */
    public const DAFTAR = [
        'mcu'     => '/miners/mcu',
        'induksi' => '/miners/induksi',
        'permit'  => '/miners/permit',
        'simper'  => '/miners/simper',
    ];

    /**
     * Alamat rincian sebuah dokumen — untuk surel dan daftar antrean.
     *
     * Pengajuan lanjutan SIMPER kini punya halaman rinciannya sendiri.
     */
    public static function tautan(object $d): string
    {
        return match ($d::jenisDokumen()) {
            'ajuan'               => '/miners/simper-lanjutan/'.$d->getKey(),
            'perpanjangan_permit' => '/miners/perpanjangan-permit/'.$d->getKey(),
            default               => (self::DAFTAR[$d::jenisDokumen()] ?? '/miners').'/'.$d->getKey(),
        };
    }

    /**
     * Kolom tabel persetujuan — kolomPersetujuan() Safe Track.
     *
     * Judul kolom adalah JABATAN beserta organisasinya ("HSE PT X"),
     * bukan nama orang; nama dicetak di dalam kotaknya. Langkah pengaju
     * yang sudah dikirim terbaca "Diajukan", bukan "Disetujui" — pengaju
     * mengajukan, tidak menyetujui pengajuannya sendiri.
     *
     * @return list<array<string,mixed>>
     */
    public static function alur(object $d): array
    {
        $berjalan = $d->langkahBerjalan();

        return $d->alur->map(function (Alur $a) use ($d, $berjalan) {
            $keadaan = $a->keadaan;

            $label = match (true) {
                $a->peran === 'pengaju' && $keadaan === 'setuju' => 'Diajukan',
                $berjalan && $berjalan->is($a)                    => 'Diproses',
                default                                           => Alur::KEADAAN[$keadaan] ?? $keadaan,
            };

            return [
                'urutan'     => $a->urutan,
                'peran'      => $a->peran,
                'sebutan'    => Alur::SEBUTAN[$a->peran] ?? '',
                'label'      => Jalur::labelLangkah($d, $a),
                'peranLabel' => Alur::PERAN[$a->peran] ?? $a->peran,
                'keadaan'    => $keadaan,
                'keadaanLabel' => $label,
                'giliran'    => $berjalan !== null && $berjalan->is($a),
                'oleh'       => $a->user?->name,
                'ditugaskan' => $a->ditugaskan?->name,
                'atasNama'   => $a->user_id && $a->ditugaskan_user_id
                    && (int) $a->user_id !== (int) $a->ditugaskan_user_id,
                'pada'       => $a->bertindak_pada ? Waktu::lokal($a->bertindak_pada)->format('d-m-Y H:i') : null,
                'catatan'    => $a->catatan,
            ];
        })->values()->all();
    }
}
