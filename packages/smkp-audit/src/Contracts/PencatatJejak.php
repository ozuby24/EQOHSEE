<?php

namespace Eqohsee\SmkpAudit\Contracts;

/**
 * Pencatat jejak aktivitas.
 *
 * Modul hanya menyebut APA yang terjadi; ke mana catatannya pergi adalah
 * urusan aplikasi induk — tabel activity log, Laravel Log, atau tidak ke
 * mana-mana. Bawaannya diam, sehingga modul tetap jalan pada aplikasi yang
 * belum punya pencatatan sama sekali.
 */
interface PencatatJejak
{
    public function catat(string $aksi, ?string $rincian = null): void;
}
