<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\User;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Batas organisasi & operasional inventarisasi GRK satu tahun.
 *
 * Laporan GRK, ISO 14064, dan PSPK 2 tidak dapat terbit sebelum batas
 * tahun itu disetujui: angka emisi tanpa batas yang dinyatakan tidak dapat
 * diperiksa verifikator mana pun.
 */
#[ScopedBy(MilikPerusahaan::class)]
class GrkBatas extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_grk_batas';

    public const PENDEKATAN = [
        'kendali_operasional' => 'Kendali operasional',
        'kendali_finansial'   => 'Kendali finansial',
        'ekuitas'             => 'Bagian ekuitas',
    ];

    protected $fillable = ['company_id', 'tahun', 'pendekatan', 'persen_ekuitas', 'uraian_batas',
                           'pengecualian', 'relevansi_scope3', 'disetujui_oleh', 'disetujui_pada'];

    protected function casts(): array
    {
        return ['tahun' => 'integer', 'persen_ekuitas' => 'float', 'pengecualian' => 'array',
                'relevansi_scope3' => 'array', 'disetujui_pada' => 'datetime'];
    }

    public function penyetuju(): BelongsTo { return $this->belongsTo(User::class, 'disetujui_oleh'); }

    public function disetujui(): bool { return $this->disetujui_pada !== null; }
}
