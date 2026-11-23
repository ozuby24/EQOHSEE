<?php

namespace App\Models\Lca;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Lca\Katalog;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/** Satu studi LCA: goal & scope, total dampak per unit fungsional, status verifikasi. */
#[ScopedBy(MilikPerusahaan::class)]
class Studi extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'lca_studi';

    protected $fillable = ['company_id', 'user_id', 'induk_id', 'kode', 'versi', 'judul', 'produk', 'sku', 'fasilitas', 'wilayah',
        'fu_nilai', 'fu_label', 'aliran_ref_kg', 'batas_sistem', 'metode', 'alokasi', 'cutoff_pct', 'periode_mulai',
        'periode_selesai', 'volume_tahunan', 'baseline_gwp', 'target_gwp', 'total_gwp', 'total_ap', 'total_ep', 'total_wsf',
        'total_ced', 'total_odp', 'dqr', 'status', 'tujuan', 'audiens', 'pemverifikasi', 'diverifikasi_at', 'catatan_tinjau'];

    protected function casts(): array
    {
        return ['fu_nilai' => 'float', 'aliran_ref_kg' => 'float', 'cutoff_pct' => 'float', 'volume_tahunan' => 'float',
            'baseline_gwp' => 'float', 'target_gwp' => 'float', 'total_gwp' => 'float', 'total_ap' => 'float', 'total_ep' => 'float',
            'total_wsf' => 'float', 'total_ced' => 'float', 'total_odp' => 'float', 'dqr' => 'float',
            'periode_mulai' => 'date', 'periode_selesai' => 'date', 'diverifikasi_at' => 'datetime'];
    }

    public function inventori(): HasMany { return $this->hasMany(Inventori::class, 'studi_id'); }
    public function hasil(): HasMany { return $this->hasMany(Hasil::class, 'studi_id'); }
    public function skenario(): HasMany { return $this->hasMany(Skenario::class, 'studi_id'); }
    public function jejak(): HasMany { return $this->hasMany(Jejak::class, 'studi_id'); }
    public function induk(): BelongsTo { return $this->belongsTo(self::class, 'induk_id'); }

    public function terkunci(): bool { return in_array($this->status, Katalog::TERKUNCI, true); }

    /** Emisi tahunan produk (t CO₂e) = GWP per FU × volume tahunan. */
    public function tonTahunan(): float { return round($this->total_gwp * $this->volume_tahunan / 1000, 2); }
}
