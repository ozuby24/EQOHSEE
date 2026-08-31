<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Lokasi kejadian — pit, ramp, workshop, jalan hauling.
 *
 * Satu-satunya master modul ini yang berbatas perusahaan. Matriks
 * risiko dan kamus penyebab berlaku sama di mana pun; nama pit tidak.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Lokasi extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'inv_lokasi';

    protected $fillable = ['company_id', 'kode', 'nama', 'area', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }
}
