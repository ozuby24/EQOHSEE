<?php

namespace App\Models\Miners;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bagian sebuah blok — front, bay, seam.
 *
 * BERKOLOM PEMILIK SENDIRI, tidak menumpang bloknya. Batasnya semula
 * menumpang lewat BerindukPerusahaan, dengan alasan yang terdengar
 * masuk akal: "pemiliknya tidak pernah berbeda dari bloknya". Yang
 * luput adalah bahwa bloknya justru TIDAK PUNYA pemilik — daftar
 * lokasi kerja 001-SPM-007 adalah acuan bersama, company_id-nya NULL —
 * sehingga batas yang menumpang ke sana tidak membatasi apa pun.
 * "Front A" yang ditambahkan satu tambang akan tampil pada daftar
 * pilih setiap tambang lain, tanpa satu galat pun.
 */
#[ScopedBy(MilikPerusahaan::class)]
class SubBlok extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'mnr_sub_blok';

    protected $guarded = ['id'];

    public function blok(): BelongsTo
    {
        return $this->belongsTo(Blok::class, 'blok_id');
    }
}
