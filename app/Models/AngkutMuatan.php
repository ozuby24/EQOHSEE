<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Angkutan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil timbang satu rit.
 *
 * Tidak memakai alur tinjauan, dan itu disengaja. Baris ini bukan
 * pendapat melainkan pembacaan alat — jembatan timbang atau payload
 * meter — dan menahannya sampai ditinjau berarti menahan satu-satunya
 * data yang dapat menunjukkan truk bermuatan 130% turun dari pit
 * kemarin sore.
 */
#[ScopedBy(MilikPerusahaan::class)]
class AngkutMuatan extends Model
{
    use BerpemilikPerusahaan;

    protected $fillable = [
        'company_id', 'angkut_regu_id', 'angkut_alat_id',
        'rit_ke', 'muatan_ton', 'waktu_timbang', 'sumber',
    ];

    protected function casts(): array
    {
        return [
            'muatan_ton'    => 'float',
            'waktu_timbang' => 'datetime',
        ];
    }

    public function regu(): BelongsTo { return $this->belongsTo(AngkutRegu::class, 'angkut_regu_id'); }
    public function alat(): BelongsTo { return $this->belongsTo(AngkutAlat::class, 'angkut_alat_id'); }

    public function persen(): ?float
    {
        return Angkutan::persenMuatan($this->muatan_ton, (float) ($this->alat?->kapasitas_ton ?? 0));
    }

    /** Melampaui batas mutlak 120% kapasitas nominal. */
    public function melampauiPuncak(): bool
    {
        $p = $this->persen();

        return $p !== null && $p > Angkutan::PAYLOAD_PUNCAK;
    }

    public function toView(): array
    {
        return [
            'id'      => $this->id,
            'reguId'  => $this->angkut_regu_id,
            'reguKode'=> $this->regu?->kode,
            'alatId'  => $this->angkut_alat_id,
            'alat'    => $this->alat?->kode,
            'ritKe'   => $this->rit_ke,
            'muatan'  => $this->muatan_ton,
            'nominal' => $this->alat?->kapasitas_ton,
            'persen'  => $this->persen(),
            'lampauiPuncak' => $this->melampauiPuncak(),
            'waktu'   => $this->waktu_timbang?->format('d M Y H:i'),
            'sumber'  => $this->sumber,
        ];
    }
}
