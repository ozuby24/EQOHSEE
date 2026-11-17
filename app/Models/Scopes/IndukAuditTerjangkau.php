<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Temuan dan daftar hadir audit SMKP tidak punya company_id sendiri;
 * pemiliknya adalah audit induknya. `whereHas` membawa global scope
 * SmkpAudit (JangkauanAudit) ikut serta, sehingga batas perusahaannya
 * tidak perlu ditulis dua kali.
 *
 * Tidak memakai BerindukPerusahaan karena yang itu melewatkan seluruh
 * administrator — termasuk administrator yang sedang melihat satu
 * perusahaan, yang di modul audit harus dibatasi seperti pengguna
 * perusahaan itu.
 */
class IndukAuditTerjangkau implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $u = auth()->user();
        if (!$u || !method_exists($u, 'jangkauan') || $u->jangkauan() === null) return;

        $builder->whereHas('audit');
    }
}
