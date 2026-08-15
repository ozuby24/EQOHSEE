<?php

namespace App\Models;

use App\Support\Energi;
use Illuminate\Database\Eloquent\Model;

/** Rekonsiliasi bahan bakar harian: disalurkan versus tercatat terpakai. */
class EnergyFuelRecon extends Model
{
    protected $table = 'energy_fuel_recon';

    protected $fillable = ['tanggal','disalurkan_liter','stok_awal_liter','stok_akhir_liter','catatan'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'disalurkan_liter' => 'float',
                'stok_awal_liter' => 'float', 'stok_akhir_liter' => 'float'];
    }

    /** Yang seharusnya terpakai menurut pergerakan stok. */
    public function terpakaiMenurutStok(): float
    {
        return $this->stok_awal_liter + $this->disalurkan_liter - $this->stok_akhir_liter;
    }

    public function selisihPersen(float $tercatat): float
    {
        return Energi::selisihRekonsiliasi($this->terpakaiMenurutStok(), $tercatat);
    }
}
