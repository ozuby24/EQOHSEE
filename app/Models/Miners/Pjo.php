<?php

namespace App\Models\Miners;

class Pjo extends Master
{
    protected $table = 'mnr_pjo';

    /** Tabelnya tidak berkolom `urutan` — lihat Master::$punyaUrutan. */
    protected bool $punyaUrutan = false;
}
