<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Produksi harian — pembagi seluruh angka intensitas energi. */
class EnergyProduction extends Model
{
    protected $table = 'energy_production';

    protected $fillable = ['tanggal','ton','bcm'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'ton' => 'float', 'bcm' => 'float'];
    }
}
