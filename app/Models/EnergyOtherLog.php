<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/** Sumber energi selain solar dan listrik, mis. gas. */
#[ScopedBy(MilikPerusahaan::class)]
class EnergyOtherLog extends Model
{
    use BerpemilikPerusahaan, \App\Models\Concerns\CatatanEnergi;

    protected $table = 'energy_other_logs';

    protected $fillable = ['company_id','tanggal','jenis','satuan','jumlah','keterangan',
                           'site_id','status','sumber_data','diinput_oleh','diverifikasi_oleh','diverifikasi_pada','alasan_tolak'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'jumlah' => 'float', 'diverifikasi_pada' => 'datetime'];
    }
}
