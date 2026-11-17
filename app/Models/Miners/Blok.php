<?php

namespace App\Models\Miners;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** Blok kerja — PIT, Stock ROM, Workshop, Jetty, dan seterusnya. */
class Blok extends Master
{
    protected $table = 'mnr_blok';

    public function sub(): HasMany
    {
        return $this->hasMany(SubBlok::class, 'blok_id')->orderBy('urutan')->orderBy('nama');
    }
}
