<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy(MilikPerusahaan::class)]
class MineOperationalRecord extends Model
{
    use BerpemilikPerusahaan;

    protected $fillable = [
        'company_id', 'user_id', 'tanggal', 'shift', 'pit', 'area', 'material',
        'produksi_ton', 'overburden_bcm', 'jarak_angkut_km', 'jumlah_truk',
        'jumlah_excavator', 'jam_operasi', 'jam_delay', 'status', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'produksi_ton' => 'float',
            'overburden_bcm' => 'float',
            'jarak_angkut_km' => 'float',
            'jumlah_truk' => 'integer',
            'jumlah_excavator' => 'integer',
            'jam_operasi' => 'float',
            'jam_delay' => 'float',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
