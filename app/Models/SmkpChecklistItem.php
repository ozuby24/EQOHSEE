<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmkpChecklistItem extends Model
{
    protected $fillable = [
        'smkp_checklist_category_id',
        'grup_kode',
        'grup_nama',
        'nomor',
        'pertanyaan',
        'petunjuk',
        'bobot',
        'urutan',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(SmkpChecklistCategory::class, 'smkp_checklist_category_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SmkpChecklistAnswer::class);
    }
}
