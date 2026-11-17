<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pertanyaan atau jawaban pada satu materi.
 *
 * Satu tabel untuk keduanya, dibedakan `parent_id`. Tabel terpisah untuk
 * jawaban akan menggandakan kolom yang sama persis dan memaksa setiap
 * halaman menggabungkan dua kueri hanya untuk menggambar satu utas.
 */
class MaterialDiscussion extends Model
{
    protected $fillable = ['material_id', 'user_id', 'parent_id', 'body'];

    public function material(): BelongsTo { return $this->belongsTo(Material::class); }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function jawaban(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }
}
