<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmkpChecklistCategory extends Model
{
    protected $fillable = ['kode', 'nama', 'bobot', 'urutan'];

    public function items(): HasMany
    {
        return $this->hasMany(SmkpChecklistItem::class)->orderBy('urutan');
    }
}
