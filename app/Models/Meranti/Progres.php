<?php

namespace App\Models\Meranti;

/** MERANTI — Progres mingguan rehabilitasi DAS (P0/P1/P2 per blok). */
class Progres extends Baris
{
    public const KUNCI = 'progres_mingguan';

    protected $table = 'meranti_progres';
}
