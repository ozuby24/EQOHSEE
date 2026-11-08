<?php

namespace App\Models\Miners;

/**
 * Hasil pemeriksaan kesehatan beserta nilainya.
 *
 * `layak` TIDAK diturunkan dari `nilai`, dan itu pembedaan yang
 * menentukan: "Fit With Note" bernilai di bawah Fit penuh tetapi
 * orangnya tetap boleh bekerja. Diturunkan dari angka, hasil itu akan
 * menolak permit bagi orang yang sesungguhnya layak — penolakan yang
 * tidak menimbulkan galat dan hanya terlihat oleh yang ditolak.
 */
class HasilMcu extends Master
{
    protected $table = 'mnr_hasil_mcu';

    protected function casts(): array
    {
        return parent::casts() + ['nilai' => 'integer', 'layak' => 'boolean'];
    }

    /**
     * Id "Belum Dinilai" — keadaan awal setiap manpower yang ditambahkan,
     * bukan sebuah keputusan (`result::belumDinilai()` Safe Track).
     *
     * Dicari lewat NAMA, bukan id: id master berbeda di tiap pemasangan,
     * dan Safe Track sudah tiga kali jatuh karena angka yang dipaku ke
     * kunci asing.
     */
    public static function belumDinilai(): ?int
    {
        $id = static::withoutGlobalScopes()->whereRaw('LOWER(nama) = ?', ['belum dinilai'])
            ->orderByRaw('company_id IS NOT NULL')->value('id');

        return $id ? (int) $id : null;
    }

    /** Id "Rujukan" — orang yang ditahan untuk pemeriksaan lanjutan. */
    public static function rujukan(): ?int
    {
        $id = static::withoutGlobalScopes()->whereRaw('LOWER(nama) = ?', ['rujukan'])
            ->orderByRaw('company_id IS NOT NULL')->value('id');

        return $id ? (int) $id : null;
    }

    /** Baris ini adalah "Belum Dinilai". */
    public function belum(): bool
    {
        return mb_strtolower(trim((string) $this->nama)) === 'belum dinilai';
    }

    /**
     * Pilihan yang boleh ditetapkan dokter: seluruh master KECUALI
     * "Belum Dinilai" (`result::pilihanDokter()` Safe Track).
     *
     * @return \Illuminate\Support\Collection<int,static>
     */
    public static function pilihanDokter()
    {
        return static::query()->terpakai()->get(['id', 'nama', 'layak', 'nilai'])
            ->reject(fn (self $h) => $h->belum())->values();
    }

    /** @return array<int,string> hanya hasil yang membolehkan kartu terbit. */
    public static function pilihanLayak(): array
    {
        return static::query()->where('layak', true)->orderBy('urutan')->pluck('nama', 'id')->all();
    }
}
