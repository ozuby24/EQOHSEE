<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kategori checklist prakualifikasi SMKP — data acuan tetap.
 *
 * Tujuh belas baris: LEGALITAS ditambah A sampai P. Sengaja TANPA
 * company_id: daftar pertanyaannya sama bagi setiap perusahaan yang
 * memakai pemasangan ini. Yang menjadi milik perusahaan adalah
 * jawabannya, dan jawaban mewarisi batasnya dari PJP yang menjawab.
 */
class SmkpChecklistCategory extends Model
{
    /**
     * Kode kategori gerbang wajib.
     *
     * Ditulis sebagai tetapan, bukan sebagai teks 'LEGALITAS' yang
     * disebar di model dan pengendali. Kategori ini dikecualikan dari
     * skor di empat tempat berbeda; satu yang salah ketik tidak
     * menimbulkan galat — ia hanya diam-diam memasukkan dokumen
     * legalitas ke dalam skor 178 dan mengubah setiap angka di layar.
     */
    public const LEGALITAS = 'LEGALITAS';

    /** Jumlah bobot kategori A–P. Dipakai pada keterangan di layar. */
    public const TOTAL_BOBOT = 178;

    protected $fillable = ['kode', 'nama', 'bobot', 'urutan'];

    public function items(): HasMany
    {
        return $this->hasMany(SmkpChecklistItem::class)->orderBy('urutan');
    }

    /** Kategori berbobot saja — LEGALITAS dinilai terpisah. */
    public function berbobot(): bool
    {
        return $this->kode !== self::LEGALITAS;
    }
}
