<?php

namespace App\Models\Rdas;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/** Satu pengukuran Petak Ukur (IS 5%). Riwayat — tidak pernah diubah. */
#[ScopedBy(MilikPerusahaan::class)]
class Pantau extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'rdas_pantau';

    protected $fillable = ['company_id', 'petak_id', 'blok_id', 'user_id', 'kode', 'tanggal', 'periode', 'jumlah_pu', 'luas_pu_ha', 'intensitas_pct', 'diamati', 'sehat', 'merana', 'mati', 'persen_tumbuh', 'sehat_ha', 'hidup_ha', 'tinggi_cm', 'diameter_mm', 'status', 'kebutuhan_sulam', 'pengamat', 'catatan'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'jumlah_pu' => 'integer', 'luas_pu_ha' => 'float', 'intensitas_pct' => 'float', 'diamati' => 'integer', 'sehat' => 'integer', 'merana' => 'integer', 'mati' => 'integer', 'persen_tumbuh' => 'float', 'sehat_ha' => 'float', 'hidup_ha' => 'float', 'tinggi_cm' => 'float', 'diameter_mm' => 'float', 'kebutuhan_sulam' => 'integer'];
    }

    public function petak(): BelongsTo { return $this->belongsTo(Petak::class, 'petak_id'); }
    public function blok(): BelongsTo { return $this->belongsTo(Blok::class, 'blok_id'); }
}
