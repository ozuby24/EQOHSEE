<?php

namespace App\Models;

use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

use App\Support\Energi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Garis dasar dan sasaran intensitas energi untuk satu tahun. */
#[ScopedBy(MilikPerusahaan::class)]
class EnergyBaseline extends Model
{
    protected $table = 'energy_baselines';

    protected $fillable = ['company_id','tahun','baseline_gj_ton','target_gj_ton','catatan'];

    protected function casts(): array
    {
        return ['tahun' => 'integer', 'baseline_gj_ton' => 'float', 'target_gj_ton' => 'float'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    /** Penurunan yang dituju: baseline terhadap target. */
    public function penurunanTarget(): float
    {
        return Energi::penurunan($this->baseline_gj_ton, $this->target_gj_ton);
    }

    /** Penurunan yang sudah dicapai terhadap baseline. */
    public function penurunanTercapai(float $sekarang): float
    {
        return Energi::penurunan($this->baseline_gj_ton, $sekarang);
    }

    /**
     * Kemajuan menuju sasaran, 0..1.
     *
     * Diukur pada rentang baseline→target, bukan terhadap baseline saja:
     * yang ingin diketahui adalah seberapa jauh perjalanan yang sudah
     * ditempuh dari seluruh jarak yang direncanakan.
     */
    public function kemajuan(float $sekarang): float
    {
        $rentang = $this->baseline_gj_ton - $this->target_gj_ton;
        if ($rentang <= 0) return 0.0;

        return max(0.0, min(1.0, ($this->baseline_gj_ton - $sekarang) / $rentang));
    }
}
