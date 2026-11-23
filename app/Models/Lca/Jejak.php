<?php

namespace App\Models\Lca;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/** Jejak audit perubahan studi LCA — hanya ditambah. */
#[ScopedBy(MilikPerusahaan::class)]
class Jejak extends Model
{
    use BerpemilikPerusahaan;

    public const UPDATED_AT = null;
    protected $table = 'lca_jejak';
    protected $fillable = ['company_id', 'studi_id', 'user_id', 'aksi', 'ringkas', 'lama', 'baru', 'ip', 'created_at'];
    protected function casts(): array { return ['lama' => 'array', 'baru' => 'array', 'created_at' => 'datetime']; }

    public static function catat($studi, string $aksi, string $ringkas, ?array $lama = null, ?array $baru = null): void
    {
        static::withoutGlobalScopes()->create([
            'company_id' => $studi?->company_id, 'studi_id' => $studi?->id, 'user_id' => auth()->id(),
            'aksi' => $aksi, 'ringkas' => mb_substr($ringkas, 0, 255), 'lama' => $lama, 'baru' => $baru,
            'ip' => request()?->ip(), 'created_at' => now(),
        ]);
    }
}
