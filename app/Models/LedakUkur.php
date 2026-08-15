<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Getaran terukur pada satu titik untuk satu peledakan.
 *
 * Jarak disimpan di sini, bukan diambil dari titiknya: muka peledakan
 * berpindah tiap kali, sehingga jarak ke rumah yang sama berbeda pada
 * tiap peledakan. Memakai satu jarak tetap membuat kalibrasi tetapan
 * situs meleset justru pada besaran yang paling menentukannya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class LedakUkur extends Model
{
    use BerpemilikPerusahaan;

    protected $fillable = [
        'company_id', 'ledak_rencana_id', 'ledak_titik_id',
        'jarak_m', 'ppv_mm_s', 'frekuensi_hz', 'airblast_db', 'alat_ukur',
    ];

    protected function casts(): array
    {
        return [
            'jarak_m'      => 'float',
            'ppv_mm_s'     => 'float',
            'frekuensi_hz' => 'float',
            'airblast_db'  => 'float',
        ];
    }

    public function rencana(): BelongsTo { return $this->belongsTo(LedakRencana::class, 'ledak_rencana_id'); }
    public function titik(): BelongsTo   { return $this->belongsTo(LedakTitik::class, 'ledak_titik_id'); }

    public function melampaui(): bool
    {
        return $this->titik !== null && $this->ppv_mm_s > $this->titik->ambang();
    }

    public function toView(): array
    {
        return [
            'id'        => $this->id,
            'rencana'   => $this->rencana?->kode,
            'titik'     => $this->titik?->nama,
            'jarak_m'   => $this->jarak_m,
            'ppv'       => $this->ppv_mm_s,
            'ambang'    => $this->titik?->ambang(),
            'melampaui' => $this->melampaui(),
            'frekuensi' => $this->frekuensi_hz,
            'airblast'  => $this->airblast_db,
            'alat'      => $this->alat_ukur,
        ];
    }
}
