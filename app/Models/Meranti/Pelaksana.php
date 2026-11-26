<?php

namespace App\Models\Meranti;

/** MERANTI — Master pelaksana (vendor/in house). */
class Pelaksana extends Baris
{
    public const KUNCI = 'master_pelaksana';

    protected $table = 'meranti_pelaksana';
}
