<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\User;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Jejak audit per baris data energi: siapa, kapan, nilai lama → baru. */
#[ScopedBy(MilikPerusahaan::class)]
class Jejak extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_jejak';

    public const UPDATED_AT = null;

    protected $fillable = ['company_id', 'subjek_type', 'subjek_id', 'aksi', 'perubahan', 'user_id', 'ip'];

    protected function casts(): array { return ['perubahan' => 'array']; }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
