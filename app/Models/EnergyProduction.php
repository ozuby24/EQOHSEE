<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/** Produksi harian — pembagi seluruh angka intensitas energi. */
#[ScopedBy(MilikPerusahaan::class)]
class EnergyProduction extends Model
{
    use BerpemilikPerusahaan, \App\Models\Concerns\CatatanEnergi;

    protected $table = 'energy_production';

    protected $fillable = ['company_id','tanggal','ton','bcm','stripping_ratio','jarak_angkut_km',
                           'status','sumber_data','diinput_oleh','diverifikasi_oleh','diverifikasi_pada','alasan_tolak'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'ton' => 'float', 'bcm' => 'float', 'stripping_ratio' => 'float',
                'jarak_angkut_km' => 'float', 'diverifikasi_pada' => 'datetime'];
    }
}
