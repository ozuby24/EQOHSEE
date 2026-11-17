<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu pertanyaan checklist prakualifikasi SMKP — data acuan tetap.
 *
 * Seratus dua puluh enam baris, masing-masing berbobot. `grup_kode` dan
 * `grup_nama` mengelompokkan pertanyaan di dalam satu kategori (kategori
 * J sendiri memuat 58 pertanyaan); keduanya boleh kosong pada kategori
 * yang memang tidak bergrup.
 */
class SmkpChecklistItem extends Model
{
    protected $fillable = [
        'smkp_checklist_category_id', 'grup_kode', 'grup_nama',
        'nomor', 'pertanyaan', 'petunjuk', 'bobot', 'urutan',
    ];

    protected function casts(): array
    {
        return ['bobot' => 'integer', 'nomor' => 'integer', 'urutan' => 'integer'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SmkpChecklistCategory::class, 'smkp_checklist_category_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(SmkpChecklistAnswer::class);
    }
}
