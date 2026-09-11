<?php

namespace App\Support\Hr;

use App\Models\Miners\Pekerja;
use App\Support\Miners\Keadaan;
use Illuminate\Support\Carbon;

/**
 * Bolehkah orang ini dijadwalkan bekerja pada tanggal itu.
 *
 * INI YANG MEMBUAT ROSTER DI SINI BERBEDA DARI SPREADSHEET, dan dari
 * HRIS mana pun yang tidak memegang berkas K3-nya: jadwal disusun
 * terhadap MCU, induksi, Mine Permit, dan SIMPER yang memang tersimpan
 * di aplikasi yang sama. HRIS yang hanya menyimpan data kepegawaian
 * harus menanyakannya ke sistem lain — dan yang ditanyakan lewat
 * integrasi selalu tertinggal beberapa jam, tepat pada berkas yang
 * baru saja dicabut.
 *
 * DINILAI PADA TANGGAL YANG DIRENCANAKAN, bukan pada hari ini. Roster
 * disusun untuk bulan depan, dan MCU yang masih berlaku hari ini dapat
 * habis di tengah periode kerja yang sedang direncanakan. Dinilai
 * terhadap hari ini, penyusunnya melihat seluruh baris hijau lalu
 * menerbitkan jadwal yang separuhnya tidak boleh dijalankan — dan
 * kesalahan itu baru ketahuan di pos jaga, dengan tiket yang sudah
 * terbit dan orangnya sudah di bandara.
 *
 * MEMBEDAKAN "TIDAK BOLEH" DARI "PERLU DIURUS". Berkas yang habis
 * menolak penjadwalan; berkas yang akan habis di tengah periode hanya
 * diperingatkan. Digabung, penyusun roster kehilangan seluruh regu
 * karena satu sertifikat yang masih berlaku tiga minggu lagi.
 */
final class Kelayakan
{
    /** Berkas yang diperiksa, berurut sesuai rantainya. */
    public const BERKAS = [
        'mcu'     => 'MCU',
        'induksi' => 'Induksi',
        'permit'  => 'Mine Permit',
        'simper'  => 'SIMPER',
    ];

    /**
     * Halangan penjadwalan seorang pekerja pada sebuah tanggal.
     *
     * SIMPER TIDAK MENGHALANGI, dan itu bukan kelalaian: tidak semua
     * pekerja mengemudikan unit. Admin logistik tanpa SIMPER tetap
     * boleh masuk selama Mine Permit-nya berlaku — memblokirnya berarti
     * menolak setiap orang yang bukan operator.
     *
     * @return array{halangan:?string,label:?string,peringatan:list<string>}
     */
    public static function periksa(Pekerja $p, Carbon $tanggal): array
    {
        $keadaan = [
            'mcu'     => Keadaan::mcu($p->mcu->first(), $tanggal),
            'induksi' => Keadaan::induksi($p->induksi->first(), $tanggal),
            'permit'  => Keadaan::permit($p->permit->first(), $tanggal),
            'simper'  => Keadaan::simper($p->simper->first(), $tanggal),
        ];

        $halangan   = null;
        $peringatan = [];

        foreach (['mcu', 'induksi', 'permit'] as $jenis) {
            $k = $keadaan[$jenis];

            if ($halangan === null && in_array($k, [Keadaan::HABIS, Keadaan::BELUM], true)) {
                $halangan = $jenis;
            }
        }

        foreach ($keadaan as $jenis => $k) {
            if ($k === Keadaan::MENDEKATI) $peringatan[] = $jenis;
        }

        /* Pekerja yang sudah tidak aktif tidak dapat dijadwalkan sama
           sekali, apa pun keadaan berkasnya. Diperiksa TERAKHIR supaya
           ia menimpa sebab lain: "sudah resign" menjelaskan lebih
           banyak daripada "MCU habis" bagi orang yang memang sudah
           pergi. */
        if (! $p->aktif()) $halangan = 'nonaktif';

        return [
            'halangan'   => $halangan,
            'label'      => $halangan === null ? null : self::label($halangan),
            'peringatan' => $peringatan,
            'keadaan'    => $keadaan,
        ];
    }

    public static function label(string $halangan): string
    {
        return match ($halangan) {
            'nonaktif' => 'Pekerja sudah tidak aktif',
            'mcu'      => 'MCU habis atau belum ada',
            'induksi'  => 'Induksi belum lulus atau habis',
            'permit'   => 'Mine Permit habis atau belum terbit',
            'simper'   => 'SIMPER habis atau belum terbit',
            default    => $halangan,
        };
    }

    /**
     * Muat relasi yang dibutuhkan pemeriksaan, sekali jalan.
     *
     * Disebut tegas sebagai daftar, bukan diserahkan pada pemuatan malas:
     * satu regu berisi puluhan orang, dan tiap pemeriksaan membaca empat
     * relasi — tanpa ini, menyusun roster sebulan untuk tiga regu
     * menghasilkan ribuan kueri untuk sebuah layar.
     *
     * @return list<string>
     */
    public static function relasi(): array
    {
        return ['mcu', 'induksi', 'permit.mcuOrang', 'simper.permit.mcuOrang'];
    }
}
