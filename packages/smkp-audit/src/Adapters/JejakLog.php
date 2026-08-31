<?php

namespace Eqohsee\SmkpAudit\Adapters;

use Eqohsee\SmkpAudit\Contracts\PencatatJejak;
use Illuminate\Support\Facades\Log;

/**
 * Menulis jejak ke kanal log Laravel.
 *
 * Berguna sebagai langkah pertama sebelum aplikasi induk menyediakan tabel
 * jejaknya sendiri: perbuatan yang mengubah audit tetap terekam di suatu
 * tempat, bukan hilang begitu saja.
 */
class JejakLog implements PencatatJejak
{
    public function catat(string $aksi, ?string $rincian = null): void
    {
        Log::info('[smkp] '.$aksi, array_filter([
            'rincian' => $rincian,
            'user_id' => auth()->id(),
        ], fn ($v) => $v !== null));
    }
}
