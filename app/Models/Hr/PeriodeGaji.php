<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Satu periode gaji, satu perusahaan, satu bulan.
 *
 * PERIODE YANG TERKUNCI TIDAK DAPAT DIHITUNG ULANG. Sesudah slip
 * dibagikan dan uangnya ditransfer, menghitung ulang berarti angka di
 * layar berbeda dari angka di rekening pekerja — dan yang bertanya
 * "kenapa beda" tidak akan pernah mendapat jawaban yang dapat
 * ditunjukkan.
 */
#[ScopedBy(MilikPerusahaan::class)]
class PeriodeGaji extends Model
{
    use BerpemilikPerusahaan;

    public const STATUS = [
        'draft'     => 'Draf',
        'terhitung' => 'Sudah dihitung',
        'terkunci'  => 'Terkunci',
    ];

    public const BULAN = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
        5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    protected $table = 'pay_periode';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'tahun'         => 'integer',
            'bulan'         => 'integer',
            'dihitung_pada' => 'datetime',
            'dikunci_pada'  => 'datetime',
        ];
    }

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function penghitung(): BelongsTo { return $this->belongsTo(User::class, 'dihitung_oleh'); }
    public function pengunci(): BelongsTo   { return $this->belongsTo(User::class, 'dikunci_oleh'); }

    public function slip(): HasMany
    {
        return $this->hasMany(SlipGaji::class, 'periode_id');
    }

    public function terkunci(): bool
    {
        return $this->status === 'terkunci';
    }

    /** Bulan rekonsiliasi progresif, bukan tarif efektif. */
    public function rekonsiliasi(): bool
    {
        return $this->bulan === \App\Support\Hr\Pajak::BULAN_REKONSILIASI;
    }

    public function label(): string
    {
        return (self::BULAN[$this->bulan] ?? $this->bulan).' '.$this->tahun;
    }

    public function scopeTerbaru(Builder $q): Builder
    {
        return $q->orderByDesc('tahun')->orderByDesc('bulan');
    }
}
