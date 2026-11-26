<?php

namespace App\Models\Meranti;

/** MERANTI — Jaminan reklamasi dan pascatambang. */
class Jaminan extends Baris
{
    public const KUNCI = 'jaminan_reklamasi';

    protected $table = 'meranti_jaminan';
}
