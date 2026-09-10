<?php

namespace App\Models\Pjp;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Penilaian kinerja perusahaan jasa, satu baris per semester.
 *
 * Tiga sumbu dinilai terpisah — teknis, keselamatan & kesehatan, dan
 * lingkungan — dan disimpan terpisah pula, bukan sebagai satu angka
 * rata-rata. Yang ditanya pemegang IUP saat memutuskan perpanjangan
 * kontrak adalah sumbu MANA yang jatuh; rata-rata 70 dapat berarti tiga
 * angka 70 atau berarti 100, 100, dan 10.
 */
class Evaluasi extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'pjp';

    public const SEMESTER = [
        1 => 'Semester 1 (Januari – Juni)',
        2 => 'Semester 2 (Juli – Desember)',
    ];

    protected $table = 'pjp_evaluasi';

    protected $fillable = [
        'pjp_id', 'tahun', 'semester',
        'skor_teknis', 'skor_keselamatan_kesehatan', 'skor_lingkungan',
        'catatan',
    ];

    protected $appends = ['skor_rata_rata'];

    protected function casts(): array
    {
        return [
            'tahun'                      => 'integer',
            'semester'                   => 'integer',
            'skor_teknis'                => 'integer',
            'skor_keselamatan_kesehatan' => 'integer',
            'skor_lingkungan'            => 'integer',
        ];
    }

    public function pjp()
    {
        return $this->belongsTo(Pjp::class, 'pjp_id');
    }

    protected function skorRataRata(): Attribute
    {
        return Attribute::make(
            get: fn () => round((
                $this->skor_teknis
                + $this->skor_keselamatan_kesehatan
                + $this->skor_lingkungan
            ) / 3, 1),
        );
    }
}
