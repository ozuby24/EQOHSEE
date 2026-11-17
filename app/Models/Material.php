<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends Model
{
    protected $fillable = [
        'course_id', 'module_id', 'title', 'description',
        'outcomes', 'prerequisite', 'duration_minutes',
        'type', 'url', 'content', 'sop_url', 'order_index',
    ];

    /**
     * Larik, bukan tali teks berpemisah.
     *
     * `outcomes` digambar sebagai daftar bercentang, dan memecah tali
     * teks pada tiap baris di sisi Vue berarti aturan pemisahnya hidup
     * di tempat yang tidak dapat diuji sisi server. Disimpan sebagai
     * larik, yang membacanya menerima bentuk yang sudah benar.
     */
    protected function casts(): array
    {
        return ['outcomes' => 'array'];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function lampiran(): HasMany
    {
        return $this->hasMany(MaterialAttachment::class)->orderBy('order_index');
    }

    /** Hanya pertanyaan — jawabannya diambil lewat relasi `jawaban`. */
    public function pertanyaan(): HasMany
    {
        return $this->hasMany(MaterialDiscussion::class)->whereNull('parent_id')->latest();
    }
}
