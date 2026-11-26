<?php

namespace App\Models\Meranti;

/** MERANTI — Fitur peta spasial (satu geometri GeoJSON per baris). */
class Peta extends Baris
{
    public const KUNCI = 'peta_spasial';

    protected $table = 'meranti_peta';
}
