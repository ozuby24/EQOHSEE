<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, BelongsToMany, HasMany};

/**
 * Satu utas percakapan.
 *
 * Sekarang hanya jenis 'bantuan' yang dipakai; 'langsung' dan 'grup' memakai
 * tabel yang sama supaya daftar, hitungan belum dibaca, dan lampiran tidak
 * perlu ditulis ulang untuk tiap jenis.
 */
class Percakapan extends Model
{
    protected $table = 'percakapan';

    protected $fillable = ['jenis', 'judul', 'company_id', 'status', 'pesan_terakhir_at'];

    protected $casts = ['pesan_terakhir_at' => 'datetime'];

    public function pesan(): HasMany
    {
        return $this->hasMany(Pesan::class);
    }

    public function peserta(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'percakapan_peserta')
            ->withPivot('dibaca_sampai_id')->withTimestamps();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Pesan yang belum dibaca seorang peserta. */
    public function belumDibaca(User $u): int
    {
        $batas = $this->peserta()->where('users.id', $u->id)->first()?->pivot?->dibaca_sampai_id;

        return $this->pesan()
            ->when($batas, fn ($q) => $q->where('id', '>', $batas))
            // Pesan sendiri tidak pernah dihitung belum dibaca.
            ->where(fn ($q) => $q->whereNull('user_id')->orWhere('user_id', '!=', $u->id))
            ->count();
    }
}
