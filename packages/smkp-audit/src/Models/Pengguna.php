<?php

namespace Eqohsee\SmkpAudit\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model bayangan pengguna — pasangan Perusahaan di atas.
 *
 * Audit menyimpan user_id pembuatnya. Aplikasi yang menyetel
 * config('smkp.model.pengguna') memakai modelnya sendiri; yang tidak,
 * memakai kelas ini agar relasi tetap sah tanpa memaksa nama kelas tertentu.
 */
class Pengguna extends Model
{
    protected $guarded = [];

    public function getTable(): string
    {
        return (string) config('smkp.tabel_pengguna', 'users');
    }
}
