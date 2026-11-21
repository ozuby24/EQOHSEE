<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/** Program konservasi energi tahunan (PP 33/2023). */
#[ScopedBy(MilikPerusahaan::class)]
class Program extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_program';

    public const STATUS = ['rencana', 'berjalan', 'selesai', 'batal'];

    protected $fillable = ['company_id', 'tahun', 'judul', 'sasaran', 'target_hemat_gj', 'target_hemat_tco2e',
                           'anggaran_rp', 'pic', 'status', 'peluang_ids'];

    protected function casts(): array
    {
        return ['tahun' => 'integer', 'target_hemat_gj' => 'float', 'target_hemat_tco2e' => 'float',
                'anggaran_rp' => 'float', 'peluang_ids' => 'array'];
    }
}
