<?php

namespace App\Models\Miners;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Concerns\PunyaAlur;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/** Satu permohonan induksi, memuat banyak peserta. */
#[ScopedBy(MilikPerusahaan::class)]
class Induksi extends Model
{
    use BerpemilikPerusahaan;
    use PunyaAlur;

    protected static string $jenisDokumen = 'induksi';

    public const STATUS = [
        'draf'     => 'Draf',
        'diajukan' => 'Diajukan',
        'dijadwal' => 'Dijadwalkan',
        'selesai'  => 'Selesai',
        'ditolak'  => 'Ditolak',
    ];

    protected $table = 'mnr_induksi';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draf'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }

    public function orang(): HasMany
    {
        return $this->hasMany(InduksiOrang::class, 'induksi_id')->orderBy('id');
    }
}
