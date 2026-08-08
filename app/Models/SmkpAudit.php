<?php

namespace App\Models;

use App\Support\Smkp;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class SmkpAudit extends Model
{
    protected $table = 'smkp_audits';

    protected $fillable = [
        'company_id', 'tahun', 'judul', 'status',
        'tanggal_mulai', 'tanggal_selesai', 'ketua_auditor',
        'hasil', 'auditor', 'profil', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'hasil'           => 'array',
            'auditor'         => 'array',
            'profil'          => 'array',
            'tanggal_mulai'   => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function findings(): HasMany  { return $this->hasMany(SmkpFinding::class, 'audit_id'); }

    /** Penilaian satu kriteria: kode penilaian (sesuai|minor|mayor|na) atau null. */
    public function nilai(string $kodeKriteria): ?string
    {
        return $this->hasil[$kodeKriteria]['n'] ?? null;
    }

    public function ket(string $kodeKriteria): string
    {
        return (string) ($this->hasil[$kodeKriteria]['ket'] ?? '');
    }

    public function bukti(string $kodeKriteria): string
    {
        return (string) ($this->hasil[$kodeKriteria]['bukti'] ?? '');
    }

    /** Rekap penuh — didelegasikan ke mesin hitung agar rumus hanya ada di satu tempat. */
    public function rekap(): array
    {
        return Smkp::rekap($this->hasil ?? []);
    }

    /** Persentase kriteria yang sudah dinilai (0..1) — untuk bilah kemajuan. */
    public function kemajuan(): float
    {
        $r = $this->rekap();
        return $r['berlaku'] > 0 ? $r['dinilai'] / $r['berlaku'] : 0.0;
    }
}
