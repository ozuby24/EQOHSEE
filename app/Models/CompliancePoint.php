<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu butir kewajiban — pasal/ayat, klausul ISO, atau persyaratan
 * dokumen — beserta penilaiannya.
 *
 * `status` boleh NULL, dan NULL berarti BELUM DINILAI. Itu bukan sama
 * dengan 'N/A': N/A adalah keputusan penilai bahwa butir ini tidak
 * mengikat kegiatan perusahaan, sedangkan NULL adalah pekerjaan yang
 * belum dikerjakan. Register yang memberi butir baru status 'N/A'
 * sebagai nilai awal terbaca sebagai register yang sudah selesai
 * dievaluasi dan kebetulan tidak ada satu pun yang berlaku.
 */
class CompliancePoint extends Model
{
    public const STATUS = ['Comply', 'Not Comply', 'N/A'];

    protected $fillable = [
        'subject_id', 'penunjuk', 'rangkuman', 'penerapan', 'status',
        'keterangan', 'tindak_lanjut', 'pic', 'target', 'order_index',
    ];

    protected function casts(): array
    {
        return ['target' => 'date', 'order_index' => 'integer'];
    }

    public function subject(): BelongsTo { return $this->belongsTo(ComplianceSubject::class, 'subject_id'); }

    /** Butir yang menunggu dikerjakan, beserta PIC dan targetnya. */
    public function scopeBelumComply($q) { return $q->where('status', 'Not Comply'); }

    /** Targetnya sudah lewat dan butirnya masih belum comply. */
    public function lewatTarget(): bool
    {
        return $this->status === 'Not Comply'
            && $this->target !== null
            && $this->target->isPast();
    }
}
