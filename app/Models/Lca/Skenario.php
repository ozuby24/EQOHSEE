<?php

namespace App\Models\Lca;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/** Skenario dekarbonisasi tersimpan untuk satu studi. */
#[ScopedBy(MilikPerusahaan::class)]
class Skenario extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'lca_skenario';
    protected $fillable = ['company_id', 'studi_id', 'nama', 'deskripsi', 'tuas_ebt', 'tuas_bbm', 'tuas_logistik', 'tuas_daur_ulang',
        'tuas_limbah', 'baseline', 'proyeksi', 'reduksi_pct', 'hemat_t_tahun', 'hemat_idr', 'hemat_eur', 'rincian'];

    protected function casts(): array
    {
        return ['rincian' => 'array', 'tuas_ebt' => 'float', 'tuas_bbm' => 'float', 'tuas_logistik' => 'float', 'tuas_daur_ulang' => 'float',
            'tuas_limbah' => 'float', 'baseline' => 'float', 'proyeksi' => 'float', 'reduksi_pct' => 'float', 'hemat_t_tahun' => 'float',
            'hemat_idr' => 'float', 'hemat_eur' => 'float'];
    }
}
