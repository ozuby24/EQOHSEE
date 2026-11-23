<?php

namespace App\Models\Perancah;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu kajian kelayakan teknis. Riwayat — tidak pernah diubah. */
#[ScopedBy(MilikPerusahaan::class)]
class Kajian extends Model
{
    use BerpemilikPerusahaan;

    public const PUTUSAN = ['layak', 'bersyarat', 'tidak_layak'];

    protected $table = 'perancah_kajian';

    protected $fillable = ['company_id', 'unit_id', 'putusan', 'skor', 'perlu_pe', 'kriteria', 'rekomendasi', 'peninjau', 'ditinjau_at'];

    protected function casts(): array
    {
        return ['kriteria' => 'array', 'rekomendasi' => 'array', 'perlu_pe' => 'boolean', 'skor' => 'integer', 'ditinjau_at' => 'datetime'];
    }

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class, 'unit_id'); }
}
