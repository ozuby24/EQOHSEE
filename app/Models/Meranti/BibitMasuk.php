<?php

namespace App\Models\Meranti;

/** MERANTI — Bibit yang diterima persemaian. */
class BibitMasuk extends Baris
{
    public const KUNCI = 'bibit_masuk';

    protected $table = 'meranti_bibit_masuk';
}
