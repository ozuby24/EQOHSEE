<?php

namespace App\Models\Angkat;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Kajian teknis pertambangan 6 pilar untuk satu rencana (Kepmen ESDM 1827/2018). */
#[ScopedBy(MilikPerusahaan::class)]
class Kajian extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'angkat_kajian';

    protected $fillable = ['company_id', 'rencana_id', 'kode', 'pilar', 'manual', 'skor', 'putusan', 'kesimpulan', 'peninjau', 'ditinjau_at'];

    protected function casts(): array
    {
        return ['pilar' => 'array', 'manual' => 'array', 'skor' => 'float', 'ditinjau_at' => 'datetime'];
    }

    public function rencana(): BelongsTo { return $this->belongsTo(Rencana::class, 'rencana_id'); }
}
