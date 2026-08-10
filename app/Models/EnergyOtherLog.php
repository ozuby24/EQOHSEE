<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Sumber energi selain solar dan listrik, mis. gas. */
class EnergyOtherLog extends Model
{
    protected $table = 'energy_other_logs';

    protected $fillable = ['tanggal','jenis','satuan','jumlah','keterangan'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'jumlah' => 'float'];
    }
}
