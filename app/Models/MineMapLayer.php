<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy(MilikPerusahaan::class)]
class MineMapLayer extends Model
{
    use BerpemilikPerusahaan;

    protected $fillable = ['company_id', 'user_id', 'nama', 'tipe', 'geojson', 'warna', 'status', 'catatan'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
