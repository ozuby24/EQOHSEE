<?php

namespace App\Models\Frop;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu sesi coaching — sheet Coaching Log. */
#[ScopedBy(MilikPerusahaan::class)]
class Coaching extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'frop_coaching';

    public const STATUS = ['Open', 'In Progress', 'Closed'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal'        => 'date',
            'target_selesai' => 'date',
        ];
    }

    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function observasi(): BelongsTo { return $this->belongsTo(Observasi::class); }
}
