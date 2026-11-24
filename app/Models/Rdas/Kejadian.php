<?php

namespace App\Models\Rdas;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/** Gangguan pada tanaman dan tangga sanksi administratif. */
#[ScopedBy(MilikPerusahaan::class)]
class Kejadian extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'rdas_kejadian';

    protected $fillable = ['company_id', 'ppkh_id', 'blok_id', 'user_id', 'jenis', 'tanggal', 'luas_terdampak_ha', 'uraian', 'strategi', 'ba_nomor', 'dilaporkan_at', 'status'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'luas_terdampak_ha' => 'float', 'dilaporkan_at' => 'date'];
    }

    public function ppkh(): BelongsTo { return $this->belongsTo(Ppkh::class, 'ppkh_id'); }
    public function blok(): BelongsTo { return $this->belongsTo(Blok::class, 'blok_id'); }
}
