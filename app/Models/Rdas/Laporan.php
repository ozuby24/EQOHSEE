<?php

namespace App\Models\Rdas;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/** Laporan semesteran pemegang PPKH. */
#[ScopedBy(MilikPerusahaan::class)]
class Laporan extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'rdas_laporan';

    protected $fillable = ['company_id', 'ppkh_id', 'tahun', 'semester', 'batas', 'dikirim_at', 'tanda_terima', 'catatan'];

    protected function casts(): array
    {
        return ['tahun' => 'integer', 'semester' => 'integer', 'batas' => 'date', 'dikirim_at' => 'date'];
    }

    public function ppkh(): BelongsTo { return $this->belongsTo(Ppkh::class, 'ppkh_id'); }

    /** terkirim, tepat, terlambat, jatuh_tempo, belum. */
    public function keadaan(): string
    {
        if ($this->dikirim_at) return $this->dikirim_at->lte($this->batas) ? 'tepat' : 'terlambat';
        if ($this->batas->endOfDay()->isPast()) return 'terlambat';
        return $this->batas->lte(now()->addDays(30)) ? 'jatuh_tempo' : 'belum';
    }
}
