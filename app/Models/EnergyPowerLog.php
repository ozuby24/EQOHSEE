<?php

namespace App\Models;

use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

use App\Support\Energi;
use Illuminate\Database\Eloquent\Model;

/** Pemakaian listrik satu area dari satu sumber pada satu hari. */
#[ScopedBy(MilikPerusahaan::class)]
class EnergyPowerLog extends Model
{
    protected $table = 'energy_power_logs';

    protected $fillable = ['company_id','tanggal','area','sumber','kwh','puncak_kw','jam_operasi','liter_genset'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'kwh' => 'float', 'puncak_kw' => 'float',
                'jam_operasi' => 'float', 'liter_genset' => 'float'];
    }

    public function labelArea(): string   { return Energi::AREA[$this->area] ?? ucfirst($this->area); }
    public function labelSumber(): string { return Energi::SUMBER_LISTRIK[$this->sumber] ?? strtoupper($this->sumber); }

    public function faktorBeban(): float
    {
        return Energi::faktorBeban($this->kwh, $this->puncak_kw, $this->jam_operasi);
    }

    /** Hanya bermakna untuk genset; PLN tidak membakar solar di sini. */
    public function efisiensiGenset(): float
    {
        return Energi::efisiensiGenset($this->kwh, $this->liter_genset);
    }
}
