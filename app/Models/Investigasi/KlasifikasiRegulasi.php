<?php

namespace App\Models\Investigasi;

use Illuminate\Database\Eloquent\Model;

/** Kecelakaan tambang, kejadian berbahaya, PAK — dan mana yang wajib dilaporkan. */
class KlasifikasiRegulasi extends Model
{
    protected $table = 'inv_klasifikasi_regulasi';

    protected $fillable = ['kode', 'nama', 'wajib_lapor_kait', 'keterangan', 'urutan'];

    protected function casts(): array
    {
        return ['wajib_lapor_kait' => 'boolean'];
    }
}
