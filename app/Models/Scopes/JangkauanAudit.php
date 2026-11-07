<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Batas data audit SMKP per perusahaan (porting Aurorasys), dipasang
 * sebagai global scope pada SmkpAudit.
 *
 * Berbeda dari MilikPerusahaan milik modul lain, dan sengaja: audit SMKP
 * mengikuti User::jangkauan() —
 *
 * - pemegang IUP menjangkau audit PJP yang bernaung di bawahnya
 *   (`parent_id`), PJP hanya auditnya sendiri, tidak pernah menyamping;
 * - audit TANPA perusahaan tidak terlihat oleh siapa pun selain
 *   administrator. Audit selalu milik satu auditi; baris tanpa auditi
 *   adalah sisa data lama yang perlu dipetakan administrator, bukan
 *   milik bersama.
 *
 * Administrator yang sedang melihat satu perusahaan (pemilih di bilah
 * atas) dibatasi persis seperti pengguna perusahaan itu — lihat
 * User::jangkauan().
 *
 * Tanpa pengguna (konsol, penyemai, antrean) tidak ada penyaringan.
 * Melepas batas harus ditulis tegas (`withoutGlobalScope`).
 */
class JangkauanAudit implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $u = auth()->user();
        if (!$u || !method_exists($u, 'jangkauan')) return;

        $j = $u->jangkauan();
        if ($j === null) return;

        $builder->whereIn($model->getTable().'.company_id', $j ?: [0]);
    }
}
