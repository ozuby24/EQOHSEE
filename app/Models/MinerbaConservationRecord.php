<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

#[ScopedBy(MilikPerusahaan::class)]
class MinerbaConservationRecord extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'konservasi_minerba_records';

    protected $fillable = [
        'company_id', 'periode', 'lokasi', 'komoditas', 'satuan',
        'target_produksi', 'produksi_aktual', 'material_digali',
        'recovery_percent', 'kehilangan_material', 'dilusi', 'stok_akhir',
        'mineral_ikutan', 'status', 'catatan', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'periode' => 'date',
            'target_produksi' => 'float',
            'produksi_aktual' => 'float',
            'material_digali' => 'float',
            'recovery_percent' => 'float',
            'kehilangan_material' => 'float',
            'dilusi' => 'float',
            'stok_akhir' => 'float',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function actions(): HasMany { return $this->hasMany(MinerbaConservationAction::class, 'record_id'); }

    public function capaianTarget(): float
    {
        return $this->target_produksi > 0 ? ($this->produksi_aktual / $this->target_produksi) * 100 : 0.0;
    }

    public function recoveryTerhitung(): float
    {
        return $this->material_digali > 0 ? ($this->produksi_aktual / $this->material_digali) * 100 : $this->recovery_percent;
    }
}
