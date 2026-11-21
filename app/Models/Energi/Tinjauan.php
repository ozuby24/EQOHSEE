<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/** Monev tahunan ke manajemen puncak (Permen ESDM 8/2025 Ps. 10) & tinjauan manajemen ISO 50001. */
#[ScopedBy(MilikPerusahaan::class)]
class Tinjauan extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_tinjauan';

    public const JENIS = ['monev' => 'Monev ke manajemen puncak', 'tinjauan_manajemen' => 'Tinjauan manajemen ISO 50001',
                          'tinjauan_energi' => 'Tinjauan energi'];

    protected $fillable = ['company_id', 'tahun', 'jenis', 'tanggal', 'peserta', 'masukan', 'keluaran'];

    protected function casts(): array { return ['tahun' => 'integer', 'tanggal' => 'date']; }
}
