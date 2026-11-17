<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Pekerja;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Satu pengajuan cuti atau izin.
 *
 * SALDO DIPOTONG SAAT DISETUJUI, BUKAN SAAT DIAJUKAN — tetapi
 * pengajuan yang masih menunggu tetap diperhitungkan saat memeriksa
 * kecukupan saldo. Dipotong saat diajukan, pengajuan yang ditolak
 * memakan saldo selamanya; tidak diperhitungkan sama sekali, dua
 * pengajuan yang menunggu dapat disetujui berdua dan saldonya menjadi
 * minus tanpa satu galat pun.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Cuti extends Model
{
    use BerpemilikPerusahaan;

    public const STATUS = [
        'menunggu'   => 'Menunggu persetujuan',
        'disetujui'  => 'Disetujui',
        'ditolak'    => 'Ditolak',
        'dibatalkan' => 'Dibatalkan',
    ];

    /** Status yang masih dapat berubah. */
    public const BERJALAN = ['menunggu'];

    protected $table = 'hr_cuti';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'menunggu', 'hari' => 0, 'kalender' => 0, 'perlu_jenjang' => false];

    protected function casts(): array
    {
        return [
            'mulai'         => 'date',
            'selesai'       => 'date',
            'hari'          => 'integer',
            'kalender'      => 'integer',
            'perlu_jenjang' => 'boolean',
            'diajukan_pada' => 'datetime',
            'ditindak_pada' => 'datetime',
        ];
    }

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function pekerja(): BelongsTo  { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }
    public function jenis(): BelongsTo    { return $this->belongsTo(JenisCuti::class, 'jenis_cuti_id'); }
    public function pengaju(): BelongsTo  { return $this->belongsTo(User::class, 'diajukan_oleh'); }
    public function penindak(): BelongsTo { return $this->belongsTo(User::class, 'ditindak_oleh'); }

    /** Baris roster yang lahir dari cuti ini. */
    public function roster(): HasMany
    {
        return $this->hasMany(Roster::class, 'cuti_id');
    }

    public function menunggu(): bool
    {
        return in_array($this->status, self::BERJALAN, true);
    }

    public function scopeMenunggu(Builder $q): Builder
    {
        return $q->whereIn('status', self::BERJALAN);
    }

    /**
     * Rentang tanggal, INKLUSIF pada kedua ujungnya.
     *
     * Alasannya sama persis dengan Roster::scopeAntara: kolomnya
     * bertipe DATE tetapi menyimpan "2026-09-22 00:00:00", dan sebagai
     * perbandingan teks "2026-09-22 00:00:00" <= "2026-09-22" bernilai
     * SALAH. Hari terakhir hilang tanpa satu galat pun.
     */
    public function scopeBersinggungan(Builder $q, string $dari, string $sampai): Builder
    {
        return $q->where('mulai', '<=', $sampai.' 23:59:59')
            ->where('selesai', '>=', $dari);
    }
}
