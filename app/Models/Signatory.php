<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penanda tangan sertifikat.
 *
 * Melekat pada perusahaan. Tanda tangan adalah pernyataan seseorang
 * bahwa ia bertanggung jawab atas isi lembar itu; memakainya lintas
 * perusahaan bukan soal kerapian data melainkan soal siapa yang
 * namanya tercantum pada dokumen yang tidak pernah ia setujui.
 *
 * company_id NULL berarti milik bersama — penanda tangan pusat yang
 * memang boleh dipakai seluruh perusahaan, dan seluruh baris lama yang
 * lahir sebelum kolom ini ada.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Signatory extends Model
{
    use BerpemilikPerusahaan;

    protected $fillable = ['company_id', 'name', 'title', 'signature', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    /**
     * Penanda tangan yang boleh dipakai perusahaan tertentu, dengan
     * miliknya sendiri lebih dulu.
     *
     * Urutannya penting, bukan kosmetik: penerbitan sertifikat mengambil
     * yang pertama dari daftar ini. Penanda tangan pusat hanya dipakai
     * bila perusahaan itu belum menetapkan penanda tangannya sendiri.
     */
    public function scopeUntukPerusahaan(Builder $q, ?int $companyId): Builder
    {
        return $q->where('is_active', true)
            ->where(function (Builder $x) use ($companyId) {
                $x->whereNull('company_id');
                if ($companyId) $x->orWhere('company_id', $companyId);
            })
            // Milik perusahaan sendiri didahulukan: yang bukan NULL
            // diurutkan lebih awal.
            ->orderByRaw('CASE WHEN company_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('id');
    }
}
