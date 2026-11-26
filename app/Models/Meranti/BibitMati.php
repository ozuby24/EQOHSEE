<?php

namespace App\Models\Meranti;

/** MERANTI — Bibit yang mati di persemaian. */
class BibitMati extends Baris
{
    public const KUNCI = 'bibit_mati';

    protected $table = 'meranti_bibit_mati';
}
