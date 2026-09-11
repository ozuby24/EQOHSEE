<?php

namespace App\Models\Miners;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy(MilikPerusahaan::class)]
class KategoriPermit extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'mnr_kategori_permit';

    protected $guarded = ['id'];

    public function tipe(): BelongsTo
    {
        return $this->belongsTo(TipePermit::class, 'tipe_permit_id');
    }
}
