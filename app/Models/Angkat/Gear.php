<?php

namespace App\Models\Angkat;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Angkat\Katalog;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{HasMany, HasOne};

/**
 * Satu alat bantu angkat: sling, rantai, webbing, shackle, spreader.
 * Masa uji 6 bulan; yang lewat masa uji atau dikarantina tidak dapat
 * dipilih di rencana pengangkatan.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Gear extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'angkat_gear';

    protected $fillable = [
        'company_id', 'kode', 'nama', 'jenis', 'wll_t', 'sf', 'diameter_mm', 'panjang_m', 'jumlah_kaki',
        'pabrikan', 'lokasi_simpan', 'kode_warna', 'uji_terakhir', 'uji_berikut', 'status', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'wll_t' => 'float', 'sf' => 'float', 'diameter_mm' => 'float', 'panjang_m' => 'float', 'jumlah_kaki' => 'integer',
            'uji_terakhir' => 'date', 'uji_berikut' => 'date',
        ];
    }

    public function riksa(): HasMany { return $this->hasMany(Riksa::class, 'gear_id'); }
    public function riksaTerakhir(): HasOne { return $this->hasOne(Riksa::class, 'gear_id')->latestOfMany('diperiksa_at'); }

    /** layak, segera (≤ 30 hari), kedaluwarsa, karantina, atau afkir. */
    public function statusEfektif(): string
    {
        if (in_array($this->status, ['karantina', 'afkir'], true)) return $this->status;
        if (! $this->uji_berikut || $this->uji_berikut->endOfDay()->isPast()) return 'kedaluwarsa';

        return $this->uji_berikut->lte(now()->addDays(30)) ? 'segera' : 'layak';
    }

    public function bolehDipakai(): bool
    {
        return in_array($this->statusEfektif(), ['layak', 'segera'], true);
    }

    public function labelJenis(): string
    {
        return Katalog::GEAR[$this->jenis]['label'] ?? $this->jenis;
    }
}
