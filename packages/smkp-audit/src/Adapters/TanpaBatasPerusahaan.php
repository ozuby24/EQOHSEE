<?php

namespace Eqohsee\SmkpAudit\Adapters;

use Eqohsee\SmkpAudit\Contracts\BatasPerusahaan;

/** Semua orang melihat semua data — aplikasi satu perusahaan. */
class TanpaBatasPerusahaan implements BatasPerusahaan
{
    public function idAktif(): int|string|null
    {
        return null;
    }

    public function lintasPerusahaan(): bool
    {
        return true;
    }
}
