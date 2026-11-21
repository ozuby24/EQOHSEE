<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\EnergyOpportunity;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Aksi mitigasi emisi — bahan registrasi SRN PPI (Perpres 110/2025). */
#[ScopedBy(MilikPerusahaan::class)]
class Mitigasi extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_mitigasi';

    public const JENIS = [
        'efisiensi' => 'Efisiensi energi', 'ebt' => 'Energi baru terbarukan', 'elektrifikasi' => 'Elektrifikasi',
        'bbn' => 'Bahan bakar nabati', 'lain' => 'Lainnya',
    ];

    public const STATUS_SRN = ['belum' => 'Belum didaftarkan', 'didaftarkan' => 'Didaftarkan', 'terverifikasi' => 'Terverifikasi'];

    protected $fillable = ['company_id', 'site_id', 'peluang_id', 'judul', 'jenis', 'mulai', 'selesai', 'metodologi',
                           'baseline_tco2e_tahun', 'target_penurunan_tco2e_tahun', 'capaian', 'status_srn',
                           'nomor_srn', 'catatan'];

    protected function casts(): array
    {
        return ['mulai' => 'date', 'selesai' => 'date', 'baseline_tco2e_tahun' => 'float',
                'target_penurunan_tco2e_tahun' => 'float', 'capaian' => 'array'];
    }

    public function site(): BelongsTo    { return $this->belongsTo(Site::class); }
    public function peluang(): BelongsTo { return $this->belongsTo(EnergyOpportunity::class, 'peluang_id'); }

    /** Capaian penurunan tCO₂e satu tahun, dari isian capaian per tahun. */
    public function capaianTahun(int $tahun): float
    {
        return (float) (($this->capaian ?? [])[(string) $tahun] ?? 0);
    }
}
