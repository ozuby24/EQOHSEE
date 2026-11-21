<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

use App\Support\Energi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/** Satu unit alat berat beserta catatan bahan bakarnya. */
#[ScopedBy(MilikPerusahaan::class)]
class EnergyEquipment extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energy_equipment';

    protected $fillable = ['company_id','kode','nama','kategori','merek','daya_hp','payload_ton','aktif',
                           'site_id','kategori_ipcc','seu'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'daya_hp' => 'integer', 'payload_ton' => 'float', 'seu' => 'boolean'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function site(): BelongsTo    { return $this->belongsTo(\App\Models\Energi\Site::class); }
    public function fuelLogs(): HasMany  { return $this->hasMany(EnergyFuelLog::class, 'equipment_id'); }

    public function labelKategori(): string
    {
        return Energi::KATEGORI[$this->kategori] ?? ucfirst($this->kategori);
    }
}
