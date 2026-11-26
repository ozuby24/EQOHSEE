<?php

namespace App\Models\Meranti;

/** MERANTI — Stok material persemaian. */
class StokMaterial extends Baris
{
    public const KUNCI = 'stok_material';

    protected $table = 'meranti_stok_material';
}
