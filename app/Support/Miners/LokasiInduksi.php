<?php

namespace App\Support\Miners;

use App\Models\Company;

/**
 * Tempat induksi dilaksanakan — App\Services\Alur\LokasiInduksi Safe Track.
 *
 * Pilihannya dibangun dari IUP, bukan nama ruangan yang ditulis tetap:
 * "Office <IUP penyelenggara>". Nilai yang sudah tersimpan tetap
 * ditawarkan walau tidak ada di daftar, supaya menyunting baris lama
 * tidak diam-diam memindahkan lokasinya ke pilihan pertama.
 *
 * Hanya IUP penyelenggara dan IUP pengguna yang sedang masuk — bukan
 * seluruh IUP pemasangan seperti Safe Track. EQOHSEE dipakai banyak
 * perusahaan sekaligus, dan daftar seluruh kantor akan membocorkan nama
 * tambang lain kepada mitra yang tidak berkepentingan.
 */
final class LokasiInduksi
{
    /** @return list<string> */
    public static function pilihan(?int $iupId = null, ?string $tersimpan = null): array
    {
        $daftar = [];

        foreach (array_unique(array_filter([$iupId, Penyetuju::iupDari(auth()->user()?->company_id)])) as $id) {
            if ($nama = Company::query()->whereKey($id)->value('name')) {
                $daftar[] = 'Office '.trim($nama);
            }
        }

        /* Tanpa satu pun IUP terbaca, daftarnya tidak boleh kosong: kotak
           pilihan yang tidak dapat dibuka menahan seluruh penjadwalan. */
        if (! $daftar) $daftar[] = 'Kantor Perusahaan';

        if (filled($tersimpan)) $daftar[] = trim((string) $tersimpan);

        return array_values(array_unique($daftar));
    }
}
