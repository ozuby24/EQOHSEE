<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Pemakaian bahan bakar satu unit pada satu hari. */
class EnergyFuelLog extends Model
{
    protected $table = 'energy_fuel_logs';

    protected $fillable = ['equipment_id','tanggal','hm','liter','idle_jam','jarak_km','ton','bcm','cycle_menit'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'hm' => 'float', 'liter' => 'float', 'idle_jam' => 'float',
                'jarak_km' => 'float', 'ton' => 'float', 'bcm' => 'float', 'cycle_menit' => 'float'];
    }

    public function equipment(): BelongsTo { return $this->belongsTo(EnergyEquipment::class, 'equipment_id'); }

    /** Liter per jam operasi — ukuran kehausan yang paling langsung. */
    public function literPerHm(): float { return $this->hm > 0 ? $this->liter / $this->hm : 0.0; }
}
