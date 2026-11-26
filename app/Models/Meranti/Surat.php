<?php

namespace App\Models\Meranti;

/** MERANTI — Register surat masuk/keluar. */
class Surat extends Baris
{
    public const KUNCI = 'surat';

    protected $table = 'meranti_surat';
}
