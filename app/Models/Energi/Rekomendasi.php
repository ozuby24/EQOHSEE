<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\EnergyOpportunity;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rekomendasi audit energi.
 *
 * Tenggatnya bawaan tiga tahun sejak audit selesai — Permen ESDM 8/2025
 * Ps. 16 — dan rekomendasi yang dinyatakan tidak layak wajib beralasan.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Rekomendasi extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_rekomendasi';

    public const STATUS = ['terbuka', 'ditindaklanjuti', 'selesai', 'tidak_layak'];
    public const PRIORITAS = ['rendah', 'sedang', 'tinggi'];

    protected $fillable = ['company_id', 'audit_id', 'peluang_id', 'uraian', 'potensi_hemat_gj', 'potensi_hemat_rp',
                           'biaya_rp', 'prioritas', 'tenggat', 'status', 'alasan_tidak_layak'];

    protected function casts(): array
    {
        return ['potensi_hemat_gj' => 'float', 'potensi_hemat_rp' => 'float', 'biaya_rp' => 'float',
                'tenggat' => 'date'];
    }

    public function audit(): BelongsTo   { return $this->belongsTo(Audit::class, 'audit_id'); }
    public function peluang(): BelongsTo { return $this->belongsTo(EnergyOpportunity::class, 'peluang_id'); }

    public function terbuka(): bool { return in_array($this->status, ['terbuka', 'ditindaklanjuti'], true); }

    public function lewatTenggat(): bool
    {
        return $this->terbuka() && $this->tenggat !== null && $this->tenggat->lt(now()->startOfDay());
    }
}
