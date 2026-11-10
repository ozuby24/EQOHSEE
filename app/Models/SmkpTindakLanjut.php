<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu kiriman tindak lanjut temuan oleh auditi. Tidak bercakupan sendiri:
 * selalu dibaca lewat temuannya, yang membawa batas perusahaan audit induk.
 */
class SmkpTindakLanjut extends Model
{
    protected $table = 'smkp_tindak_lanjut';

    /** Folder lampiran di disk privat 'local'; satu folder per audit, lalu per temuan. */
    public const FOLDER = 'tindak-lanjut';

    protected $fillable = ['finding_id', 'user_id', 'akar_masalah', 'tindakan', 'bukti_perbaikan', 'penanggung_jawab', 'lampiran'];

    protected function casts(): array
    {
        return ['lampiran' => 'array'];
    }

    public function temuan(): BelongsTo
    {
        return $this->belongsTo(SmkpFinding::class, 'finding_id');
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public static function folder(int $auditId, ?int $temuanId = null): string
    {
        return self::FOLDER.'/'.$auditId.($temuanId ? '/'.$temuanId : '');
    }
}
