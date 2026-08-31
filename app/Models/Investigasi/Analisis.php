<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;

/** Satu analisis penyebab dengan satu metode — SCAT, ICAM, Tripod, HFACS. */
class Analisis extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'investigasi';

    protected $table = 'inv_analisis';

    protected $fillable = ['investigasi_id', 'metode', 'catatan'];

    public function investigasi() { return $this->belongsTo(Investigasi::class, 'investigasi_id'); }

    public function pilihan()
    {
        return $this->hasMany(ScatPilihan::class, 'analisis_id');
    }
}
