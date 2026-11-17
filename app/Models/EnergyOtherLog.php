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
    use BerpemilikPerusahaan;

    protected $table = 'energy_other_logs';

    protected $fillable = ['company_id','tanggal','jenis','satuan','jumlah','keterangan'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'jumlah' => 'float'];
    }
}
