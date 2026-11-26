<?php

namespace App\Models\Meranti;

/** MERANTI — Arsip tautan berkas capaian. */
class ArsipCapaian extends Baris
{
    public const KUNCI = 'actual_capaian';

    protected $table = 'meranti_arsip_capaian';
}
