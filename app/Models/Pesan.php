<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pesan extends Model
{
    protected $table = 'pesan';

    protected $fillable = ['percakapan_id', 'user_id', 'peran', 'isi'];

    /** Peran pengirim; menentukan sisi dan warna gelembung di layar. */
    public const PERAN = ['pengguna', 'asisten', 'admin', 'sistem'];

    public function percakapan(): BelongsTo
    {
        return $this->belongsTo(Percakapan::class);
    }

    public function pengirim(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
