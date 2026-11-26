<?php

namespace App\Models\Meranti;

/** MERANTI — Rencana reklamasi tahunan. */
class RencanaReklamasi extends Baris
{
    public const KUNCI = 'rencana_reklamasi';

    protected $table = 'meranti_rencana';
}
