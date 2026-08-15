<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris riwayat revisi dokumen.
 *
 * Berkas revisi lama tetap disimpan supaya jejak perubahan dapat ditelusuri
 * saat audit — bukti bahwa dokumen kedaluwarsa memang pernah berlaku.
 */
class DocumentRevision extends Model
{
    protected $fillable = ['document_id', 'revisi', 'ringkasan_perubahan', 'berkas', 'tanggal', 'oleh'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'revisi' => 'integer'];
    }

    public function document(): BelongsTo { return $this->belongsTo(Document::class); }

    public function labelRevisi(): string
    {
        return 'Rev. ' . str_pad((string) $this->revisi, 2, '0', STR_PAD_LEFT);
    }
}
