<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmkpChecklistAnswer extends Model
{
    public const JAWABAN = [
        'ya' => 'Y',
        'tidak' => 'T',
        'na' => 'N/A',
    ];

    public const NILAI = [
        '0' => '0 - Tidak ada / tidak tersedia / tidak dijelaskan',
        '1' => '1 - Persyaratan belum terpenuhi',
        '2' => '2 - Persyaratan cukup memadai tetapi perlu perbaikan',
        '3' => '3 - Persyaratan sudah memadai',
        'na' => 'N/A - Persyaratan tidak berlaku',
    ];

    protected $fillable = ['pjp_id', 'smkp_checklist_item_id', 'jawaban', 'nilai', 'penjelasan'];

    public function pjp(): BelongsTo
    {
        return $this->belongsTo(Pjp::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SmkpChecklistItem::class, 'smkp_checklist_item_id');
    }
}
