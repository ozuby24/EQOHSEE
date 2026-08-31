<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;

/** Satu temuan investigasi, beserta rekomendasinya. */
class Temuan extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'investigasi';

    protected $table = 'inv_temuan';

    public const TINGKAT = ['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi'];

    protected $fillable = [
        'no_temuan', 'investigasi_id', 'akar_id', 'uraian', 'rekomendasi', 'tingkat', 'urutan',
    ];

    public function investigasi() { return $this->belongsTo(Investigasi::class, 'investigasi_id'); }
    public function akar()        { return $this->belongsTo(AkarMasalah::class, 'akar_id'); }

    public function tindakan()
    {
        return $this->hasMany(Tindakan::class, 'temuan_id')->orderBy('id');
    }
}
