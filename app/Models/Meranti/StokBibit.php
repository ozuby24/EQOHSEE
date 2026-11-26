<?php

namespace App\Models\Meranti;

/** MERANTI — Stok bibit per jenis di persemaian. */
class StokBibit extends Baris
{
    public const KUNCI = 'stok_bibit';

    protected $table = 'meranti_stok_bibit';
}
