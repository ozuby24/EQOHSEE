<?php

namespace Eqohsee\SmkpAudit\Adapters;

use Eqohsee\SmkpAudit\Contracts\PencatatJejak;

/** Tidak mencatat apa pun — bawaan bagi aplikasi tanpa jejak aktivitas. */
class JejakDiam implements PencatatJejak
{
    public function catat(string $aksi, ?string $rincian = null): void
    {
        //
    }
}
