<?php

namespace App\Models\Meranti;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/** Setelan MERANTI per perusahaan — folder Google Drive arsip capaian. */
#[ScopedBy(MilikPerusahaan::class)]
class Setelan extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'meranti_setelan';

    protected $fillable = ['company_id', 'folder_drive', 'catatan'];
}
