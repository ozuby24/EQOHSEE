<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris daftar hadir rapat pembukaan atau penutupan audit Tahap II.
 */
class SmkpAttendee extends Model
{
    protected $table = 'smkp_attendees';

    protected $fillable = ['audit_id', 'rapat', 'nama', 'jabatan', 'perusahaan', 'tanda_tangan'];

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SmkpAudit::class, 'audit_id');
    }
}
