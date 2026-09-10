<?php

namespace App\Models\Pjp;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu butir pertanyaan daftar periksa prakualifikasi SMKP.
 *
 * Data acuan seperti kategorinya — lihat SmkpKategori.
 */
class SmkpItem extends Model
{
    protected $table = 'pjp_smkp_item';

    protected $fillable = [
        'kategori_id', 'grup_kode', 'grup_nama',
        'nomor', 'pertanyaan', 'petunjuk', 'bobot', 'urutan',
    ];

    public function kategori()
    {
        return $this->belongsTo(SmkpKategori::class, 'kategori_id');
    }

    public function jawaban()
    {
        return $this->hasMany(SmkpJawaban::class, 'item_id');
    }
}
