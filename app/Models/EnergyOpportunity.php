<?php

namespace App\Models;

use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

use App\Support\Energi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Peluang penghematan energi yang sedang ditindaklanjuti. */
#[ScopedBy(MilikPerusahaan::class)]
class EnergyOpportunity extends Model
{
    protected $table = 'energy_opportunities';

    protected $fillable = ['company_id','judul','area','status','uraian','hemat_liter','hemat_kwh',
                           'penanggung_jawab','target_selesai','user_id'];

    protected function casts(): array
    {
        return ['hemat_liter' => 'float', 'hemat_kwh' => 'float', 'target_selesai' => 'date'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    /* Penghematan per bulan, diturunkan dari satuan asalnya. */
    public function gj(): float    { return Energi::literKeGj($this->hemat_liter) + Energi::kwhKeGj($this->hemat_kwh); }
    public function tco2e(): float { return Energi::literKeCo2($this->hemat_liter) + Energi::kwhKeCo2($this->hemat_kwh); }
    public function rupiah(): float{ return Energi::literKeRp($this->hemat_liter) + Energi::kwhKeRp($this->hemat_kwh); }

    /** Sudah terwujud, jadi boleh dihitung sebagai penghematan nyata. */
    public function terwujud(): bool { return in_array($this->status, ['berjalan','selesai'], true); }
}
