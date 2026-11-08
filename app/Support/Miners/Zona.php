<?php

namespace App\Support\Miners;

use App\Support\Waktu;
use Illuminate\Support\Carbon;

/**
 * Zona masa berlaku — MasaBerlakuService Safe Track.
 *
 *   expired  sudah lewat
 *   kritis   0–30 hari lagi
 *   waspada  31–60 hari lagi
 *   aman     lebih dari 60 hari lagi
 *   kosong   tanggalnya belum diisi
 *
 * Dipakai bersama oleh daftar Manpower, Mine Permit, SIMPER, dan layar
 * monitoring: kartu zona, saring zona, rekap per perusahaan, dan lencana
 * tiap baris harus membaca ambang yang SAMA, atau satu orang tercatat
 * "≤ 30 hari" di kartu dan "> 60 hari" di tabel di bawahnya.
 *
 * Dibandingkan pada tanggal TAMBANG (Waktu::kini), bukan UTC: kartu yang
 * habis tanggal 1 pukul 00.00 waktu setempat tidak boleh terbaca masih
 * berlaku delapan jam lagi.
 */
final class Zona
{
    public const BATAS_KRITIS  = 30;
    public const BATAS_WASPADA = 60;

    public const KUNCI = ['expired', 'kritis', 'waspada', 'aman', 'kosong'];

    /** @return array<string,array{pendek:string,label:string,hex:string}> */
    public static function daftar(): array
    {
        return [
            'expired' => ['pendek' => 'Expired',     'label' => 'Lewat masa berlaku',        'hex' => '#dc3545'],
            'kritis'  => ['pendek' => '≤ 30 hari',   'label' => 'Kurang dari 30 hari',       'hex' => '#fd7e14'],
            'waspada' => ['pendek' => '31-60 hari',  'label' => '31 - 60 hari lagi',         'hex' => '#ffc107'],
            'aman'    => ['pendek' => '> 60 hari',   'label' => 'Lebih dari 60 hari lagi',   'hex' => '#198754'],
            'kosong'  => ['pendek' => 'Belum diisi', 'label' => 'Tanggal belum diisi',       'hex' => '#adb5bd'],
        ];
    }

    /** Sisa hari sampai tanggal itu; negatif bila sudah lewat, null bila kosong. */
    public static function sisa(mixed $tanggal): ?int
    {
        if ($tanggal === null || $tanggal === '' || $tanggal === '0000-00-00') return null;

        $t = $tanggal instanceof \DateTimeInterface
            ? Carbon::instance($tanggal)
            : Carbon::parse((string) $tanggal);

        return (int) Waktu::kini()->startOfDay()->diffInDays($t->copy()->startOfDay(), false);
    }

    /** Zona sebuah tanggal habis. */
    public static function dari(mixed $tanggal): string
    {
        return self::dariSisa(self::sisa($tanggal));
    }

    public static function dariSisa(?int $sisa): string
    {
        return match (true) {
            $sisa === null                => 'kosong',
            $sisa < 0                     => 'expired',
            $sisa <= self::BATAS_KRITIS   => 'kritis',
            $sisa <= self::BATAS_WASPADA  => 'waspada',
            default                       => 'aman',
        };
    }

    /**
     * Hitungan per zona dari sekumpulan zona.
     *
     * @param  iterable<string>  $zona
     * @return array{total:int,expired:int,kritis:int,waspada:int,aman:int,kosong:int}
     */
    public static function ringkas(iterable $zona): array
    {
        $n = ['total' => 0] + array_fill_keys(self::KUNCI, 0);

        foreach ($zona as $z) {
            $n['total']++;
            $n[$z] = ($n[$z] ?? 0) + 1;
        }

        return $n;
    }

    /**
     * Rekap per perusahaan — rekapPerusahaan() Safe Track.
     *
     * @param  iterable<array{perusahaan:?string,zona:string,aktif?:bool}>  $baris
     * @return list<array<string,mixed>> diurut dari total terbesar
     */
    public static function rekapPerusahaan(iterable $baris, bool $pakaiAktif = false): array
    {
        $rekap = [];

        foreach ($baris as $b) {
            $nama = $b['perusahaan'] ?: '(tanpa perusahaan)';

            $rekap[$nama] ??= ['perusahaan' => $nama, 'total' => 0]
                + ($pakaiAktif ? ['aktif' => 0, 'nonaktif' => 0] : [])
                + array_fill_keys(self::KUNCI, 0);

            $rekap[$nama]['total']++;
            $rekap[$nama][$b['zona']]++;

            if ($pakaiAktif) {
                $rekap[$nama][($b['aktif'] ?? true) ? 'aktif' : 'nonaktif']++;
            }
        }

        usort($rekap, fn ($a, $b) => [$b['total'], $a['perusahaan']] <=> [$a['total'], $b['perusahaan']]);

        return array_values($rekap);
    }
}
