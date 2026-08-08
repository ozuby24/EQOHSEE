<?php

namespace App\Models;

use App\Support\Dokumen;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Document extends Model
{
    protected $fillable = [
        'kode', 'judul', 'jenis', 'klasifikasi', 'departemen', 'company_id',
        'revisi', 'status', 'tanggal_terbit', 'tanggal_berlaku', 'tanggal_tinjau',
        'berkas', 'ringkasan', 'acuan', 'disetujui_oleh', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_terbit'  => 'date',
            'tanggal_berlaku' => 'date',
            'tanggal_tinjau'  => 'date',
            'revisi'          => 'integer',
        ];
    }

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function revisions(): HasMany  { return $this->hasMany(DocumentRevision::class)->orderByDesc('revisi'); }

    /** Sudah lewat jatuh tempo peninjauan dan masih berlaku. */
    public function perluTinjau(): bool
    {
        return $this->status === 'berlaku'
            && $this->tanggal_tinjau
            && $this->tanggal_tinjau->isPast();
    }

    /** Mendekati jatuh tempo peninjauan (dalam ambang peringatan). */
    public function segeraTinjau(): bool
    {
        return $this->status === 'berlaku'
            && $this->tanggal_tinjau
            && !$this->tanggal_tinjau->isPast()
            && $this->tanggal_tinjau->diffInDays(now()) <= Dokumen::AMBANG_PERINGATAN;
    }

    /** Label revisi yang lazim dipakai pada lembar dokumen. */
    public function labelRevisi(): string
    {
        return 'Rev. ' . str_pad((string) $this->revisi, 2, '0', STR_PAD_LEFT);
    }
}
