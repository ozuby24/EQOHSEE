<?php

namespace App\Models\Rdas;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/** Log pelaksanaan lapangan dari kontraktor; dihitung hanya bila diverifikasi. */
#[ScopedBy(MilikPerusahaan::class)]
class Kegiatan extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'rdas_kegiatan';

    protected $fillable = ['company_id', 'blok_id', 'petak_id', 'user_id', 'tanggal', 'jenis', 'fase', 'luas_ha', 'bibit', 'hok', 'biaya_rp', 'pelaksana', 'lat', 'lng', 'status', 'verifikator', 'diverifikasi_at', 'catatan'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'luas_ha' => 'float', 'bibit' => 'integer', 'hok' => 'integer', 'biaya_rp' => 'float', 'lat' => 'float', 'lng' => 'float', 'diverifikasi_at' => 'datetime'];
    }

    public function blok(): BelongsTo { return $this->belongsTo(Blok::class, 'blok_id'); }
    public function petak(): BelongsTo { return $this->belongsTo(Petak::class, 'petak_id'); }
}
