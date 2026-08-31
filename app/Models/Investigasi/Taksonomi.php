<?php

namespace App\Models\Investigasi;

use Illuminate\Database\Eloquent\Model;

/** Satu butir kamus penyebab — SCAT, ICAM, Tripod, atau HFACS. */
class Taksonomi extends Model
{
    protected $table = 'inv_taksonomi';

    protected $fillable = ['metode', 'kategori', 'kode', 'label', 'tingkat', 'definisi', 'urutan'];
}
