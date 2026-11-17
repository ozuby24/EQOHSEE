<?php

namespace App\Models\Investigasi;

use Illuminate\Database\Eloquent\Model;

/**
 * Hierarki pengendalian — eliminasi sampai APD.
 *
 * Tingkatnya dipakai menilai mutu tindakan perbaikan dari jauh:
 * investigasi yang seluruh tindakannya bertingkat 4 dan 5 adalah
 * investigasi yang tidak mengubah apa pun di lapangan.
 */
class HierarkiKendali extends Model
{
    protected $table = 'inv_hierarki_kendali';

    protected $fillable = ['kode', 'nama', 'tingkat', 'keterangan'];
}
