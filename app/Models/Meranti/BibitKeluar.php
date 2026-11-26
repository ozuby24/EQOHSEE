<?php

namespace App\Models\Meranti;

/** MERANTI — Bibit yang dikirim ke blok tanam. */
class BibitKeluar extends Baris
{
    public const KUNCI = 'bibit_keluar';

    protected $table = 'meranti_bibit_keluar';
}
