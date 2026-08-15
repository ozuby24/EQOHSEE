<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Temuan audit SMKP beserta tindakan perbaikannya (CAR).
 */
class SmkpFinding extends Model
{
    use BerindukPerusahaan;

    /** Pemiliknya ada pada induknya; lihat BerindukPerusahaan. */
    protected static string $indukPerusahaan = 'audit';

    protected $table = 'smkp_findings';

    protected $fillable = [
        'audit_id', 'kode_kriteria', 'document_id', 'jenis', 'uraian', 'akar_masalah',
        'tindakan', 'penanggung_jawab', 'target_selesai', 'tanggal_selesai',
        'status', 'verifikasi',
    ];

    protected function casts(): array
    {
        return [
            'target_selesai'  => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function audit(): BelongsTo        { return $this->belongsTo(SmkpAudit::class, 'audit_id'); }
    public function document(): BelongsTo     { return $this->belongsTo(Document::class); }

    /** Temuan lewat target penyelesaian dan belum ditutup. */
    public function terlambat(): bool
    {
        return $this->status !== 'Closed'
            && $this->target_selesai
            && $this->target_selesai->isPast();
    }
}
