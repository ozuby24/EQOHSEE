<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu terbitan Sertifikat Penghargaan Kinerja Lingkungan.
 *
 * Yang dicetak dibaca dari `data` — potret saat terbit — bukan dari
 * auditnya. Lihat migrasi 2026_09_29_000001.
 */
#[ScopedBy(MilikPerusahaan::class)]
class EnvAuditSertifikat extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'env_audit_sertifikat';

    protected $fillable = [
        'audit_id', 'company_id', 'user_id', 'signatory_id',
        'nomor', 'kode', 'terbit', 'berlaku', 'tempat',
        'predikat', 'peringkat', 'skor', 'data',
        'dicabut_at', 'alasan_cabut',
    ];

    protected function casts(): array
    {
        return [
            'terbit'     => 'date',
            'berlaku'    => 'date',
            'skor'       => 'float',
            'data'       => 'array',
            'dicabut_at' => 'datetime',
        ];
    }

    /**
     * Tema warna lembar menurut peringkatnya.
     *
     * Predikat hanya terbit mulai skor 70, jadi peringkat yang mungkin
     * tercetak hanya tiga ini. MERAH dan HITAM tidak pernah bersertifikat.
     */
    public const TEMA = ['EMAS' => 'emas', 'HIJAU' => 'hijau', 'BIRU' => 'biru'];

    /** Jumlah bintang pada medali. */
    public const BINTANG = ['ADITAMA' => 3, 'UTAMA' => 2, 'PRATAMA' => 1];

    /** Huruf kode: tanpa 0/O dan 1/I/L, yang tertukar saat diketik dari kertas. */
    private const HURUF_KODE = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function audit(): BelongsTo     { return $this->belongsTo(EnvAudit::class, 'audit_id'); }
    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function signatory(): BelongsTo { return $this->belongsTo(Signatory::class); }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->whereNull('dicabut_at');
    }

    /** sah | kedaluwarsa | dicabut */
    public function status(): string
    {
        if ($this->dicabut_at) return 'dicabut';
        if ($this->berlaku && $this->berlaku->lt(now()->startOfDay())) return 'kedaluwarsa';

        return 'sah';
    }

    public function tema(): string
    {
        return self::TEMA[$this->peringkat] ?? 'hijau';
    }

    /** "7K3F-9QPX-M2HD" — dikelompokkan supaya dapat dibaca dan diketik. */
    public function kodeTampil(): string
    {
        return implode('-', str_split($this->kode, 4));
    }

    public function urlVerifikasi(): string
    {
        return route('audit-lingkungan.verifikasi', $this->kode);
    }

    public static function kodeBaru(): string
    {
        do {
            $k = '';
            for ($i = 0; $i < 12; $i++) {
                $k .= self::HURUF_KODE[random_int(0, strlen(self::HURUF_KODE) - 1)];
            }
        } while (static::withoutGlobalScopes()->where('kode', $k)->exists());

        return $k;
    }

    /**
     * AKL-SERT/CAM/2026/001 — urut per tahun terbit, lintas perusahaan.
     *
     * Dihitung tanpa batas perusahaan: nomornya unik di seluruh tabel,
     * dan hitungan yang hanya melihat baris perusahaan sendiri akan
     * memberi nomor yang sudah dipakai perusahaan lain.
     */
    public static function nomorBaru(string $prefiks, int $tahun): string
    {
        $n = static::withoutGlobalScopes()->whereYear('terbit', $tahun)->count();

        do {
            $n++;
            $nomor = sprintf('AKL-SERT/%s/%d/%03d', $prefiks, $tahun, $n);
        } while (static::withoutGlobalScopes()->where('nomor', $nomor)->exists());

        return $nomor;
    }
}
