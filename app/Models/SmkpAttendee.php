<?php

namespace App\Models;

use App\Models\Scopes\IndukAuditTerjangkau;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris daftar hadir rapat pembukaan atau penutupan Tahap II. */
#[ScopedBy(IndukAuditTerjangkau::class)]
class SmkpAttendee extends Model
{
    protected $table = 'smkp_attendees';

    protected $fillable = ['audit_id', 'rapat', 'nama', 'jabatan', 'perusahaan'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SmkpAudit::class, 'audit_id');
    }
}
