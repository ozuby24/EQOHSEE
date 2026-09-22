<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nilai satu kriteria audit lingkungan.
 *
 * `nilai` diisi mitra sebagai penilaian mandiri; `verifikasi` diisi
 * auditor sesudah memeriksa lapangan. Keduanya boleh NULL — belum
 * diisi. Yang menentukan skor akhir hanya `verifikasi`.
 */
class EnvAuditScore extends Model
{
    protected $fillable = ['audit_id', 'kode', 'nilai', 'verifikasi', 'keterangan', 'berkas'];

    protected function casts(): array
    {
        return ['nilai' => 'integer', 'verifikasi' => 'integer', 'berkas' => 'array'];
    }

    public function audit(): BelongsTo { return $this->belongsTo(EnvAudit::class, 'audit_id'); }

    /** Penilaian mandiri dan verifikasi berbeda — yang perlu ditengok auditor. */
    public function berselisih(): bool
    {
        return $this->nilai !== null
            && $this->verifikasi !== null
            && $this->nilai !== $this->verifikasi;
    }
}
