<?php

namespace App\Models\Investigasi;

use Illuminate\Database\Eloquent\Model;

/** Jenis kejadian: cedera, nyaris celaka, kerusakan unit, kebakaran, longsor. */
class JenisInsiden extends Model
{
    protected $table = 'inv_jenis_insiden';

    protected $fillable = ['kode', 'nama', 'kelompok', 'urutan'];
}
