<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\AuditLingkungan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Satu pelaksanaan Audit Kinerja Pengelolaan dan Pemantauan Lingkungan.
 */
#[ScopedBy(MilikPerusahaan::class)]
class EnvAudit extends Model
{
    use BerpemilikPerusahaan;

    public const STATUS = ['Berjalan', 'Selesai'];

    protected $fillable = [
        'company_id', 'user_id', 'kode', 'tahun', 'judul', 'lokasi',
        'tanggal', 'status', 'profil', 'pengurang', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'   => 'date',
            'tahun'     => 'integer',
            'profil'    => 'array',
            'pengurang' => 'array',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function scores(): HasMany    { return $this->hasMany(EnvAuditScore::class, 'audit_id'); }

    /**
     * Skor lengkap: per bagian, tertimbang, dikurangi, berpredikat.
     *
     * Yang dihitung kolom VERIFIKASI, bukan kolom nilai. Penilaian
     * mandiri mitra bukan skor resminya — kalau ia yang dipakai, audit
     * berubah menjadi formulir isian mandiri yang ditandatangani
     * auditor.
     */
    public function skor(): array
    {
        $rows = $this->relationLoaded('scores') ? $this->scores : $this->scores()->get();

        return AuditLingkungan::hitung(
            $rows->pluck('verifikasi', 'kode')->all(),
            (array) $this->pengurang,
        );
    }

    public static function kodeBaru(int $tahun): string
    {
        $n = static::withoutGlobalScopes()->where('tahun', $tahun)->count() + 1;

        return sprintf('AKL-%d-%03d', $tahun, $n);
    }
}
