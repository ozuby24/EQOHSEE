<?php

namespace App\Models\Meranti;

/** MERANTI — Penilaian keberhasilan reklamasi (Matrik 17). */
class Penilaian extends Baris
{
    public const KUNCI = 'penilaian_keberhasilan';

    protected $table = 'meranti_penilaian';
}
