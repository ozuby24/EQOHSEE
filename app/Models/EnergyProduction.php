<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/** Produksi harian — pembagi seluruh angka intensitas energi. */
#[ScopedBy(MilikPerusahaan::class)]
class EnergyProduction extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energy_production';

    protected $fillable = ['company_id','tanggal','ton','bcm'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'ton' => 'float', 'bcm' => 'float'];
    }
}
