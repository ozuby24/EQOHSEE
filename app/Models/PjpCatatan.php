<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PjpCatatan extends Model
{
    use HasFactory;

    protected $fillable = [
        'pjp_id',
        'isi',
    ];

    public function pjp(): BelongsTo
    {
        return $this->belongsTo(Pjp::class);
    }
}
