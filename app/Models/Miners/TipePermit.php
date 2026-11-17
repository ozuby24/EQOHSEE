<?php

namespace App\Models\Miners;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tipe permit — Full, Temporary, Visitor.
 *
 * `hari_berlaku` menyimpan lamanya menurut SOP: Visitor 7 hari,
 * Temporary paling lama 1 bulan. NULL berarti mengikuti aturan tahunan —
 * berlaku sampai 31 Desember tahun terbit.
 *
 * Disimpan di sini alih-alih sebagai cabang `match` di dalam kode supaya
 * menambah satu tipe tidak menuntut perubahan kode; dan supaya kartu
 * yang sudah terbit tetap dapat menunjukkan dasar tanggalnya.
 */
class TipePermit extends Master
{
    protected $table = 'mnr_tipe_permit';

    public function kategori(): HasMany
    {
        return $this->hasMany(KategoriPermit::class, 'tipe_permit_id')->orderBy('nama');
    }
}
