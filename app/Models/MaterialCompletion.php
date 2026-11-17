<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu materi yang sudah dituntaskan satu orang. */
class MaterialCompletion extends Model
{
    protected $fillable = ['user_id', 'material_id'];

    public function material(): BelongsTo { return $this->belongsTo(Material::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
