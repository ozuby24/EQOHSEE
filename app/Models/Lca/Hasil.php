<?php

namespace App\Models\Lca;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/** Hasil LCIA per kategori dampak per tahap — tembolok yang dihitung ulang setiap inventori berubah. */
#[ScopedBy(MilikPerusahaan::class)]
class Hasil extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'lca_hasil';
    protected $fillable = ['company_id', 'studi_id', 'kategori', 'tahap', 'nilai', 'satuan', 'porsi', 'normal'];
    protected function casts(): array { return ['nilai' => 'float', 'porsi' => 'float', 'normal' => 'float']; }
}
