<?php

namespace App\Models\Miners;

class Subkontraktor extends Master
{
    protected $table = 'mnr_subkontraktor';

    /** Tabelnya tidak berkolom `urutan` — lihat Master::$punyaUrutan. */
    protected bool $punyaUrutan = false;
}
