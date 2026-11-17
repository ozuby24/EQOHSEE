<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Jenis cuti dan izin.
 *
 * LAMANYA DISIMPAN SEBAGAI DATA, bukan ditulis di dalam kode
 * perhitungannya. Putusan MK 168/PUU-XXI/2023 memerintahkan
 * undang-undang ketenagakerjaan baru paling lambat 31 Oktober 2026;
 * angka yang tertanam di dalam kode berarti satu penerapan ulang untuk
 * tiap pasal yang berubah — dan yang lupa diubah tidak menimbulkan
 * galat, hanya cuti yang salah hitung.
 */
#[ScopedBy(MilikPerusahaan::class)]
class JenisCuti extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'hr_jenis_cuti';

    protected $guarded = ['id'];

    protected $attributes = [
        'potong_saldo'   => false,
        'berbayar'       => true,
        'perlu_bukti'    => false,
        'akrual'         => false,
        'keadaan_roster' => 'cuti',
    ];

    protected function casts(): array
    {
        return [
            'hari'            => 'integer',
            'potong_saldo'    => 'boolean',
            'berbayar'        => 'boolean',
            'perlu_bukti'     => 'boolean',
            'akrual'          => 'boolean',
            'carry_over_maks' => 'integer',
            'urutan'          => 'integer',
            'aktif'           => 'boolean',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    public function cuti(): HasMany  { return $this->hasMany(Cuti::class, 'jenis_cuti_id'); }
    public function saldo(): HasMany { return $this->hasMany(SaldoCuti::class, 'jenis_cuti_id'); }

    public function scopeTerpakai(Builder $q): Builder
    {
        return $q->where('aktif', true)->orderBy('urutan')->orderBy('kode');
    }

    /** @return array<int,string> id => label, siap menjadi daftar pilih. */
    public static function pilihan(): array
    {
        return static::query()->terpakai()->get()
            ->mapWithKeys(fn (self $j) => [$j->id => $j->nama])
            ->all();
    }
}
