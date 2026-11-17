<?php

namespace App\Models\Miners;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pola lembar cetak kartu yang disimpan — `SkemaCetak` Safe Track:
 * kertas, orientasi, sela, margin, garis potong. Isinya disimpan utuh
 * sebagai JSON pada `pengaturan`; bentuknya ditentukan paket kartu.
 */
#[ScopedBy(MilikPerusahaan::class)]
class TataCetak extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'mnr_tata_cetak';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['pengaturan' => 'array'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
