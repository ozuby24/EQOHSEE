<?php

namespace App\Models\Lca;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Faktor dampak per satuan aktivitas.
 *
 * company_id NULL = pustaka bersama (terlihat semua perusahaan karena
 * MilikPerusahaan membuka baris tanpa pemilik); baris ber-company_id
 * adalah faktor khusus perusahaan itu.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Faktor extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'lca_faktor';

    protected $fillable = ['company_id', 'kode', 'nama', 'kategori', 'tahap_bawaan', 'scope', 'satuan',
        'gwp', 'ap', 'ep', 'wsf', 'ced', 'odp', 'sumber', 'tahun', 'wilayah', 'perlu_cocok', 'aktif', 'catatan'];

    protected function casts(): array
    {
        return ['gwp' => 'float', 'ap' => 'float', 'ep' => 'float', 'wsf' => 'float', 'ced' => 'float', 'odp' => 'float',
                'scope' => 'integer', 'tahun' => 'integer', 'perlu_cocok' => 'boolean', 'aktif' => 'boolean'];
    }
}
