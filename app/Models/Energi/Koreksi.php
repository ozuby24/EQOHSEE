<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\User;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/** Pengajuan koreksi atas baris yang periodenya sudah terkunci. */
#[ScopedBy(MilikPerusahaan::class)]
class Koreksi extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_koreksi';

    protected $fillable = ['company_id', 'periode_id', 'subjek_type', 'subjek_id', 'nilai_lama', 'nilai_baru',
                           'alasan', 'status', 'diajukan_oleh', 'diputus_oleh', 'diputus_pada', 'catatan_putusan'];

    protected function casts(): array
    {
        return ['nilai_lama' => 'array', 'nilai_baru' => 'array', 'diputus_pada' => 'datetime'];
    }

    public function periode(): BelongsTo { return $this->belongsTo(Periode::class, 'periode_id'); }
    public function subjek(): MorphTo    { return $this->morphTo(); }
    public function pengaju(): BelongsTo { return $this->belongsTo(User::class, 'diajukan_oleh'); }
    public function pemutus(): BelongsTo { return $this->belongsTo(User::class, 'diputus_oleh'); }
}
