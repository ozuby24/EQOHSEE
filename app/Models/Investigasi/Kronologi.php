<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu peristiwa pada rekonstruksi kronologi.
 *
 * `penyebab` menandai peristiwa yang diduga menjadi penyebab. Tanda itu
 * bukan hiasan: kata pada peristiwa bertanda inilah yang dibaca mesin
 * saran SCAT sebagai lapis pertama.
 */
class Kronologi extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'investigasi';

    protected $table = 'inv_kronologi';

    protected $fillable = ['investigasi_id', 'waktu', 'peristiwa', 'keterangan', 'penyebab', 'urutan'];

    protected function casts(): array
    {
        return ['waktu' => 'datetime', 'penyebab' => 'boolean'];
    }

    public function investigasi() { return $this->belongsTo(Investigasi::class, 'investigasi_id'); }
}
