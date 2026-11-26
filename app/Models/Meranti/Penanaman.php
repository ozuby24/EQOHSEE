<?php

namespace App\Models\Meranti;

/** MERANTI — Aktual penanaman bibit per blok. */
class Penanaman extends Baris
{
    public const KUNCI = 'aktual_penanaman';

    protected $table = 'meranti_penanaman';
}
