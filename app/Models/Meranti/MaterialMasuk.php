<?php

namespace App\Models\Meranti;

/** MERANTI — Material yang diterima. */
class MaterialMasuk extends Baris
{
    public const KUNCI = 'material_masuk';

    protected $table = 'meranti_material_masuk';
}
