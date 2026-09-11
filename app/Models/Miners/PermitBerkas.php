<?php

namespace App\Models\Miners;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu lampiran pada sebuah Mine Permit.
 *
 * SATU BARIS PER JENIS, bukan satu kolom per jenis seperti Project1.
 * SOP merinci 9–12 lampiran yang berbeda per jenis pengajuan, dan
 * sebagai kolom, menambah satu lampiran menuntut migrasi — sehingga
 * lampiran yang diminta SOP tetapi belum punya kolom (hasil alkohol dan
 * narkoba, SIO, hasil post test) tidak pernah ditambahkan sama sekali.
 */
class PermitBerkas extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'permit';

    protected $table = 'mnr_permit_berkas';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function permit(): BelongsTo
    {
        return $this->belongsTo(Permit::class, 'permit_id');
    }
}
