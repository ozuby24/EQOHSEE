<?php

namespace App\Models\Angkat;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/** Jejak alur rencana: dibuat, diajukan, disetujui, ditolak, selesai, batal. */
#[ScopedBy(MilikPerusahaan::class)]
class Jejak extends Model
{
    use BerpemilikPerusahaan;

    public const UPDATED_AT = null;

    protected $table = 'angkat_jejak';

    protected $fillable = ['company_id', 'rencana_id', 'user_id', 'aksi', 'oleh', 'ringkas', 'created_at'];

    protected function casts(): array { return ['created_at' => 'datetime']; }

    public static function catat(Rencana $r, string $aksi, string $ringkas, ?string $oleh = null, ?int $userId = null): self
    {
        return self::create(['company_id' => $r->company_id, 'rencana_id' => $r->id, 'user_id' => $userId ?? auth()->id(),
            'aksi' => $aksi, 'oleh' => $oleh ?? auth()->user()?->name, 'ringkas' => mb_substr($ringkas, 0, 255), 'created_at' => now()]);
    }
}
