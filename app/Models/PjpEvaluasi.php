<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PjpEvaluasi extends Model
{
    use HasFactory;

    public const SEMESTER = [
        1 => 'Semester 1 (Januari - Juni)',
        2 => 'Semester 2 (Juli - Desember)',
    ];

    protected $fillable = [
        'pjp_id',
        'tahun',
        'semester',
        'skor_teknis',
        'skor_keselamatan_kesehatan',
        'skor_lingkungan',
        'catatan',
    ];

    protected $appends = ['skor_rata_rata'];

    public function pjp(): BelongsTo
    {
        return $this->belongsTo(Pjp::class);
    }

    protected function skorRataRata(): Attribute
    {
        return Attribute::make(
            get: fn () => round(($this->skor_teknis + $this->skor_keselamatan_kesehatan + $this->skor_lingkungan) / 3, 1),
        );
    }

    /**
     * Rata-rata skor evaluasi SELURUH PJP per (tahun, semester) — dipakai
     * untuk grafik tren gabungan di halaman Evaluasi, beda dari
     * EvaluasiTrendComparison yang membandingkan PJP satu per satu.
     * Dihitung dari semua data yang ada tanpa ikut filter pencarian/status
     * halaman, karena ini dimaksudkan sebagai ringkasan portofolio
     * keseluruhan, bukan tampilan yang berubah-ubah mengikuti filter.
     * `skor_rata_rata` adalah accessor PHP (bukan kolom DB), jadi
     * pengelompokan dan rata-ratanya dilakukan di memori, bukan lewat SQL
     * AVG().
     *
     * @return \Illuminate\Support\Collection<int, array{tahun: int, semester: int, rata_rata: float, jumlah_pjp: int}>
     */
    public static function averageTrend(): \Illuminate\Support\Collection
    {
        return static::all()
            ->groupBy(fn (self $evaluasi) => "{$evaluasi->tahun}-{$evaluasi->semester}")
            ->map(fn ($group) => [
                'tahun' => $group->first()->tahun,
                'semester' => $group->first()->semester,
                'rata_rata' => round($group->avg('skor_rata_rata'), 1),
                'jumlah_pjp' => $group->count(),
            ])
            ->sortBy([['tahun', 'asc'], ['semester', 'asc']])
            ->values();
    }
}
