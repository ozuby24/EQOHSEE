<?php

namespace App\Models\Meranti;

/** MERANTI — Material yang dipakai atau dikirim. */
class MaterialKeluar extends Baris
{
    public const KUNCI = 'material_keluar';

    protected $table = 'meranti_material_keluar';
}
