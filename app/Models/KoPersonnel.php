<?php

namespace App\Models;

use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[ScopedBy(MilikPerusahaan::class)]
class KoPersonnel extends Model
{
    protected $table = 'ko_personnel';

    protected $fillable = [
        'nama','jabatan','company_id','sertifikasi','no_sertifikat','tgl_kadaluarsa','user_id',
    ];

    protected function casts(): array
    {
        return ['tgl_kadaluarsa' => 'date'];
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function user()    { return $this->belongsTo(User::class); }
    public function reviews() { return $this->hasMany(KoReview::class, 'ko_personnel_id'); }

    public function getSisaHariAttribute(): ?int
    {
        if (!$this->tgl_kadaluarsa) return null;
        return (int) round(now()->startOfDay()->diffInDays(Carbon::parse($this->tgl_kadaluarsa)->startOfDay(), false));
    }

    public function getAktifAttribute(): bool
    {
        return ($this->sisa_hari ?? -1) >= 0;
    }
}
