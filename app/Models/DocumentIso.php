<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pemetaan: dokumen ini memenuhi klausul itu pada standar tersebut.
 */
class DocumentIso extends Model
{
    protected $table = 'document_iso';

    protected $fillable = ['document_id', 'standar', 'klausul'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
