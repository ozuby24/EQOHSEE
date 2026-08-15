<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy(MilikPerusahaan::class)]
class MineOperationalTarget extends Model
{
    use BerpemilikPerusahaan;

    protected $fillable = [
        'company_id', 'user_id', 'tahun', 'bulan', 'target_produksi_ton',
        'target_overburden_bcm', 'target_strip_ratio', 'target_jarak_km', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer',
            'bulan' => 'integer',
            'target_produksi_ton' => 'float',
            'target_overburden_bcm' => 'float',
            'target_strip_ratio' => 'float',
            'target_jarak_km' => 'float',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
