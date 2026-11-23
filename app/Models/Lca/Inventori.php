<?php

namespace App\Models\Lca;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris inventori daur hidup, dengan salinan faktor saat dihitung. */
#[ScopedBy(MilikPerusahaan::class)]
class Inventori extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'lca_inventori';

    protected $fillable = ['company_id', 'studi_id', 'faktor_id', 'tahap', 'nama', 'jumlah', 'satuan', 'jumlah_normal', 'jarak_km',
        'daur_ulang_pct', 'alokasi_pct', 'f_gwp', 'f_ap', 'f_ep', 'f_wsf', 'f_ced', 'f_odp', 'gwp', 'ap', 'ep', 'wsf', 'ced', 'odp',
        'p_reliabilitas', 'p_kelengkapan', 'p_temporal', 'p_geografis', 'p_teknologi', 'dqr', 'ketidakpastian', 'sumber_data', 'catatan'];

    protected function casts(): array
    {
        $f = [];
        foreach (['jumlah', 'jumlah_normal', 'jarak_km', 'daur_ulang_pct', 'alokasi_pct', 'f_gwp', 'f_ap', 'f_ep', 'f_wsf', 'f_ced', 'f_odp',
                  'gwp', 'ap', 'ep', 'wsf', 'ced', 'odp', 'dqr', 'ketidakpastian'] as $k) $f[$k] = 'float';
        return $f;
    }

    public function studi(): BelongsTo { return $this->belongsTo(Studi::class, 'studi_id'); }
    public function faktor(): BelongsTo { return $this->belongsTo(Faktor::class, 'faktor_id'); }
}
