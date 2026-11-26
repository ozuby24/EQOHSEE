<?php

namespace App\Models\Meranti;

/** MERANTI — Bibit yang rusak dan tidak layak tanam. */
class BibitRusak extends Baris
{
    public const KUNCI = 'bibit_rusak';

    protected $table = 'meranti_bibit_rusak';
}
