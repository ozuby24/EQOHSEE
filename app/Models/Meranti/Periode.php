<?php

namespace App\Models\Meranti;

/** MERANTI — Periode pengajuan dana. */
class Periode extends Baris
{
    public const KUNCI = 'periode_pengajuan';

    protected $table = 'meranti_periode';
}
