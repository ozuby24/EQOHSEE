<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;

/** Orang yang terlibat pada satu insiden — korban, saksi, atau terlibat. */
class InsidenOrang extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'insiden';

    protected $table = 'inv_insiden_orang';

    protected $fillable = [
        'insiden_id', 'nama', 'jabatan', 'perusahaan', 'peran',
        'bagian_tubuh', 'rincian_cedera', 'hari_hilang',
    ];

    public function insiden() { return $this->belongsTo(Insiden::class, 'insiden_id'); }
}
