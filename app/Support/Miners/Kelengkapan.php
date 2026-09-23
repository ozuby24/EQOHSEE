<?php

namespace App\Support\Miners;

use App\Models\Miners\{Permit, Simper};

/**
 * Kelengkapan lampiran sebuah dokumen terhadap daftar wajib SOP.
 *
 * ── Kenapa terpisah dari Acuan ──
 *
 * Acuan menyimpan DAFTARNYA — apa saja yang diminta SOP bagi tiap jenis
 * pengajuan. Yang di sini menjawab pertanyaan yang berbeda: dokumen
 * INI, dengan lampiran yang benar-benar sudah diunggah, kurang apa.
 * Daftar dan pemeriksaan disatukan, Acuan akan perlu mengenal model
 * Eloquent hanya untuk menjawab satu pertanyaan.
 *
 * ── Hanya Mine Permit dan SIMPER ──
 *
 * MCU dan induksi tidak punya daftar wajib di SOP: keduanya
 * menghasilkan hasil pemeriksaan, bukan kartu izin, dan lampirannya
 * melekat pada ORANG di dalam suratnya, bukan pada suratnya. Dokumen
 * lain memulangkan daftar kosong, yang berarti "tidak ada yang
 * kurang" — bukan "tidak diperiksa".
 */
final class Kelengkapan
{
    /**
     * Kunci daftar wajib bagi dokumen ini, atau null bila tidak diatur.
     */
    public static function kunci(object $d): ?string
    {
        if ($d instanceof Permit) {
            /* Perpanjangan dikenali dari adanya kartu terdahulu milik
               orang yang sama, bukan dari kolom tersendiri: kolom yang
               harus diisi tangan akan salah diisi, dan yang salah isi
               menagihkan daftar induksi awal kepada orang yang sudah
               bekerja tiga tahun. */
            $perpanjangan = $d->pekerja_id
                && Permit::withoutGlobalScopes()
                    ->where('pekerja_id', $d->pekerja_id)
                    ->where('id', '!=', $d->getKey())
                    ->exists();

            return Acuan::kunciBerkasPermit($d->tipe?->nama, $perpanjangan);
        }

        if ($d instanceof Simper) {
            return Acuan::kunciBerkasSimper($d->ajuan?->first()?->jenis);
        }

        return null;
    }

    /**
     * Daftar periksa lengkap: tiap lampiran wajib beserta ada/tidaknya.
     *
     * @return list<array{label:string,kunci:string,ada:bool}>
     */
    public static function daftar(object $d): array
    {
        $kunci = self::kunci($d);

        if ($kunci === null) return [];

        return Acuan::kelengkapan(Acuan::berkasWajib($kunci), self::lampiran($d));
    }

    /**
     * Label lampiran wajib yang BELUM ada.
     *
     * @return list<string>
     */
    public static function kurang(object $d): array
    {
        return array_values(array_map(
            fn (array $b) => $b['label'],
            array_filter(self::daftar($d), fn (array $b) => ! $b['ada']),
        ));
    }

    /**
     * Baris lampiran yang menempel pada dokumen ini.
     *
     * SIMPER membaca lampiran MINE PERMIT yang mendasarinya, dan itu
     * bukan jalan pintas: SOP menuntut seluruh berkas Full Permit
     * ditambah SIMPOL dan SIO bagi pengajuan SIMPER, dan berkas Full
     * Permit itu memang sudah dilampirkan pada permitnya. Menagihkannya
     * dua kali membuat mitra kerja mengunggah dokumen yang sama dua
     * kali untuk satu orang yang sama.
     */
    private static function lampiran(object $d): iterable
    {
        if ($d instanceof Permit) return $d->berkas;

        if ($d instanceof Simper) return $d->permit?->berkas ?? [];

        return [];
    }
}
