<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu penugasan Aturan Akses Audit: akun sebagai auditor atau pengunggah
 * data bagi satu perusahaan — lihat App\Support\AturanAksesAudit.
 */
class AksesAudit extends Model
{
    protected $table = 'audit_akses';

    public const AUDITOR    = 'auditor';
    public const PENGUNGGAH = 'pengunggah';

    public const PERAN = [self::AUDITOR => 'Auditor', self::PENGUNGGAH => 'Pengunggah data'];

    protected $fillable = ['company_id', 'user_id', 'peran', 'dibuat_oleh'];

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
}
