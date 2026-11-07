<?php

namespace App\Support\Miners;

use App\Models\Company;
use App\Models\Miners\{Alur, Skema};
use App\Models\User;

/**
 * Siapa yang menyetujui apa — SkemaPersetujuan Safe Track.
 *
 * PENYETUJU BERNAMA PER IUP, bukan "siapa pun pemegang peran". Sebelum
 * ini yang boleh menindak sebuah langkah adalah setiap pemegang peran di
 * perusahaan dokumennya, dan yang disurati juga setiap pemegang peran
 * itu. Dua akibatnya: dokumen buatan mitra (IUJP) menyurati OHSE milik
 * mitra itu sendiri — yang biasanya tidak ada — dan tidak ada satu orang
 * pun yang namanya tercatat sebagai yang ditunggu. Safe Track menunjuk
 * satu orang per peran per IUP, dan hanya orang itu yang dapat menyetujui.
 *
 * Bila skema IUP belum diatur, yang dipakai alur UMUM (baris tanpa
 * perusahaan) — cadangan yang sama dengan baris tanpa iup_id Safe Track.
 * Bila keduanya kosong, pemanggil mendapat null dan dapat MENGATAKAN apa
 * yang harus diisi, bukan jatuh dengan galat.
 */
final class Penyetuju
{
    /** Label peran skema untuk pesan "belum lengkap" — PERAN Safe Track. */
    public const LABEL = [
        'dokter' => 'Dokter pemeriksa MCU',
        'ohse'   => 'HSE (verifikasi data)',
        'ktt'    => 'Kepala Teknik Tambang',
    ];

    /** Skema untuk sebuah IUP, dengan alur umum sebagai cadangan. */
    public static function untuk(?int $iupId): ?Skema
    {
        $baris = $iupId ? Skema::query()->where('company_id', $iupId)->first() : null;

        return $baris ?? Skema::query()->whereNull('company_id')->first();
    }

    /**
     * IUP sebuah perusahaan: induknya bila ia mitra, atau dirinya sendiri.
     *
     * EQOHSEE menyimpan hubungan IUP–IUJP sebagai satu induk
     * (`companies.parent_id`), bukan pasangan banyak-ke-banyak seperti
     * `iup_iujp` Safe Track; IUP penerbit karena itu cukup dibaca dari
     * induknya.
     */
    public static function iupDari(?int $companyId): ?int
    {
        if (! $companyId) return null;

        $parent = Company::query()->whereKey($companyId)->value('parent_id');

        return $parent ? (int) $parent : $companyId;
    }

    /** Id penyetuju satu peran pada IUP ini, atau null. */
    public static function id(?int $iupId, string $peran): ?int
    {
        return self::untuk($iupId)?->penyetuju($peran);
    }

    /**
     * Peran skema yang belum diisi di antara yang dibutuhkan rantai.
     *
     * @param  list<string>  $rantai
     * @return list<string> label peran yang kosong
     */
    public static function yangBelumDiisi(?int $iupId, array $rantai): array
    {
        $s = self::untuk($iupId);
        $kurang = [];

        foreach ($rantai as $peran) {
            if (! isset(Skema::KOLOM[$peran])) continue;

            $id = $s?->penyetuju($peran);

            if (! $id || ! User::query()->whereKey($id)->exists()) {
                $kurang[] = self::LABEL[$peran];
            }
        }

        return $kurang;
    }

    /**
     * Pesan siap tampil ketika alur belum lengkap.
     *
     * Sengaja menyebut nama menunya, supaya petugas tahu harus ke mana —
     * pesanBelumSiap() Safe Track.
     *
     * @param  list<string>  $rantai
     */
    public static function pesanBelumSiap(?int $iupId, array $rantai): string
    {
        $kurang = self::yangBelumDiisi($iupId, $rantai);

        if ($kurang === []) return '';

        $nama = $iupId ? (Company::query()->whereKey($iupId)->value('code')
            ?: Company::query()->whereKey($iupId)->value('name')) : null;

        return 'Alur persetujuan '.($nama ?: 'IUP ini').' belum lengkap: '
            .implode(', ', $kurang).' belum ditentukan. '
            .'Isi lebih dulu di menu Miners → Alur Persetujuan.';
    }

    /**
     * Alamat penerima tambahan satu peran.
     *
     * @param  'to'|'cc'  $jenis
     * @return list<string>
     */
    public static function penerima(?int $iupId, string $peran, string $jenis = 'cc'): array
    {
        $kolom = Skema::PENERIMA[$peran][$jenis] ?? null;

        if (! $kolom) return [];

        return self::pecahEmail((string) self::untuk($iupId)?->{$kolom});
    }

    /**
     * Pecah teks berisi banyak alamat menjadi larik.
     *
     * Koma DAN titik koma sama-sama diterima: daftar alamat yang disalin
     * dari klien surat biasanya dipisah titik koma, dan menolak salah
     * satunya membuat alamat tersimpan sebagai satu teks panjang yang
     * tidak pernah terkirim.
     *
     * @return list<string>
     */
    public static function pecahEmail(?string $teks): array
    {
        $hasil = [];

        foreach (preg_split('/[,;\s]+/', (string) $teks, -1, PREG_SPLIT_NO_EMPTY) as $b) {
            if (filter_var($b, FILTER_VALIDATE_EMAIL)) $hasil[] = mb_strtolower($b);
        }

        return array_values(array_unique($hasil));
    }

    /** Jumlah alamat yang DITULIS, sah atau tidak — untuk menangkap salah ketik. */
    public static function jumlahDitulis(?string $teks): int
    {
        return count(preg_split('/[,;\s]+/', trim((string) $teks), -1, PREG_SPLIT_NO_EMPTY));
    }

    /**
     * Nama pendek perusahaan untuk judul kolom — "PT. IBP", bukan nama
     * panjangnya yang melipat tiga baris.
     */
    public static function singkat(?int $companyId): string
    {
        if (! $companyId) return '';

        $c = Company::query()->find($companyId, ['id', 'name', 'code', 'nama_pendek']);

        return (string) ($c?->nama_pendek ?: ($c?->code ?: $c?->name));
    }

    /**
     * Sebutan sebuah peran pada kolom tabel persetujuan.
     *
     * JABATAN beserta perusahaannya, bukan nama orangnya: "HSE PT X",
     * bukan "Linda". Kolom berjudul nama orang tidak lagi berarti
     * apa-apa begitu orangnya berganti, sementara dokumen lamanya tetap
     * harus terbaca.
     */
    public static function labelPeran(string $peran, ?int $iupId, ?User $pengaju = null): string
    {
        if ($peran === 'pengaju') {
            $nama = self::singkat($pengaju?->company_id ?? $iupId);

            return $nama !== '' ? 'HSE '.$nama : 'Pengaju';
        }

        $iup = self::singkat($iupId);

        return match ($peran) {
            'dokter' => trim('DR '.$iup),
            'ohse'   => trim('HSE '.$iup),
            'ktt'    => trim('KTT '.$iup),
            default  => Alur::PERAN[$peran] ?? strtoupper($peran),
        };
    }
}
