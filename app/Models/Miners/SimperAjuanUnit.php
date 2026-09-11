<?php

namespace App\Models\Miners;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Unit yang diminta pada sebuah pengajuan lanjutan. */
class SimperAjuanUnit extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'ajuan';

    protected $table = 'mnr_simper_ajuan_unit';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'nilai_p2h'     => 'integer',
            'nilai_praktek' => 'integer',
            'nilai_teori'   => 'integer',
            'nilai_rambu'   => 'integer',
        ];
    }

    public function ajuan(): BelongsTo      { return $this->belongsTo(SimperAjuan::class, 'ajuan_id'); }
    public function kendaraan(): BelongsTo  { return $this->belongsTo(Kendaraan::class, 'kendaraan_id'); }
    public function jenisUnit(): BelongsTo  { return $this->belongsTo(JenisUnit::class, 'jenis_unit_id'); }
}
