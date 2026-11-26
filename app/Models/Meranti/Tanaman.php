<?php

namespace App\Models\Meranti;

/** MERANTI — Master jenis tanaman. */
class Tanaman extends Baris
{
    public const KUNCI = 'master_tanaman';

    protected $table = 'meranti_tanaman';
}
