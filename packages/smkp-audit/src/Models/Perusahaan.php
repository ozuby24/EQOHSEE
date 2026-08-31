<?php

namespace Eqohsee\SmkpAudit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model bayangan perusahaan.
 *
 * Dipakai HANYA agar relasi SmkpAudit::company() tetap berbentuk sah pada
 * aplikasi yang belum punya model perusahaan sendiri. Ia tidak pernah
 * dikueri pada keadaan itu: controller memeriksa config('smkp.model.perusahaan')
 * lebih dulu, dan batas per perusahaan ikut tidak aktif.
 *
 * Begitu aplikasi induk menyetel modelnya sendiri, kelas ini tidak terpakai
 * sama sekali.
 */
class Perusahaan extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('smkp.perusahaan.tabel', 'companies');
    }
}
