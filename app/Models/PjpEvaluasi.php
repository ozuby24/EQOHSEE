<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Evaluasi kinerja PJP — satu baris per (PJP, tahun, semester).
 *
 * Tiga aspek dinilai terpisah, masing-masing 0–100, dan rata-ratanya
 * dihitung bukan disimpan. Menyimpan rata-rata berarti ia dapat
 * berselisih dengan ketiga angka penyusunnya setelah salah satunya
 * diperbaiki, dan yang terbaca orang adalah angka lama yang tetap
 * terlihat sah.
 */
class PjpEvaluasi extends Model
{
    use BerindukPerusahaan;
    use HasFactory;

    /** Pemiliknya ada pada induknya; lihat BerindukPerusahaan. */
    protected static string $indukPerusahaan = 'pjp';

    public const SEMESTER = [
        1 => 'Semester 1 (Januari - Juni)',
        2 => 'Semester 2 (Juli - Desember)',
    ];

    protected $fillable = [
        'pjp_id', 'tahun', 'semester',
        'skor_teknis', 'skor_keselamatan_kesehatan', 'skor_lingkungan',
        'catatan',
    ];

    protected $appends = ['skor_rata_rata'];

    protected function casts(): array
    {
        // Lihat catatan yang sama di PjpLaporan: perbandingan ketat
        // terhadap pjp_id menentukan 404 atau bukan.
        return [
            'pjp_id'                     => 'integer',
            'tahun'                      => 'integer',
            'semester'                   => 'integer',
            'skor_teknis'                => 'integer',
            'skor_keselamatan_kesehatan' => 'integer',
            'skor_lingkungan'            => 'integer',
        ];
    }

    public function pjp(): BelongsTo
    {
        return $this->belongsTo(Pjp::class);
    }

    protected function skorRataRata(): Attribute
    {
        return Attribute::make(
            get: fn () => round(
                ($this->skor_teknis + $this->skor_keselamatan_kesehatan + $this->skor_lingkungan) / 3,
                1,
            ),
        );
    }

    /** Label pendek untuk sumbu grafik dan lencana: "S1 2026". */
    public function periodeSingkat(): string
    {
        return "S{$this->semester} {$this->tahun}";
    }
}
