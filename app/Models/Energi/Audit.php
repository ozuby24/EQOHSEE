<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Audit energi — paling sedikit sekali tiap tiga tahun, mencakup ≥1 SEU. */
#[ScopedBy(MilikPerusahaan::class)]
class Audit extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_audit';

    public const STATUS = ['direncanakan', 'berjalan', 'selesai'];

    protected $fillable = ['company_id', 'jenis', 'judul', 'ruang_lingkup', 'seu', 'auditor_ids', 'mulai',
                           'selesai', 'standar', 'status', 'ringkasan'];

    protected function casts(): array
    {
        return ['seu' => 'array', 'auditor_ids' => 'array', 'mulai' => 'date', 'selesai' => 'date'];
    }

    public function rekomendasi(): HasMany { return $this->hasMany(Rekomendasi::class, 'audit_id'); }
}
