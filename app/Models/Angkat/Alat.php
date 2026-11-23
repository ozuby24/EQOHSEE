<?php

namespace App\Models\Angkat;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Angkat\Katalog;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{HasMany, HasOne};

/**
 * Satu pesawat angkat terdaftar (crane, hoist) beserta SUKET riksa ujinya.
 *
 * `status` hanya menyimpan keputusan orang — layak atau karantina.
 * Kedaluwarsa dibaca dari tanggal oleh `statusEfektif()`, sehingga crane
 * yang SUKET-nya habis tengah malam sudah terhitung tidak layak pada
 * pengangkatan pagi tanpa menunggu penjadwal mana pun.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Alat extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'angkat_alat';

    protected $fillable = [
        'company_id', 'user_id', 'kode', 'nama', 'jenis', 'pabrikan', 'model', 'tahun', 'nomor_seri',
        'kapasitas_t', 'berat_crane_t', 'boom_maks_m', 'berat_hook_t', 'lokasi',
        'suket_nomor', 'suket_terbit', 'suket_berlaku', 'operator_nama', 'operator_sio', 'sio_berlaku',
        'status', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'kapasitas_t' => 'float', 'berat_crane_t' => 'float', 'boom_maks_m' => 'float', 'berat_hook_t' => 'float',
            'tahun' => 'integer', 'suket_terbit' => 'date', 'suket_berlaku' => 'date', 'sio_berlaku' => 'date',
        ];
    }

    public function riksa(): HasMany { return $this->hasMany(Riksa::class, 'alat_id'); }
    public function riksaTerakhir(): HasOne { return $this->hasOne(Riksa::class, 'alat_id')->latestOfMany('diperiksa_at'); }
    public function rencana(): HasMany { return $this->hasMany(Rencana::class, 'alat_id'); }

    /** layak, segera (≤ 30 hari), kedaluwarsa, atau karantina. */
    public function statusEfektif(): string
    {
        if ($this->status === 'karantina') return 'karantina';
        if (! $this->suket_berlaku || $this->suket_berlaku->endOfDay()->isPast()) return 'kedaluwarsa';

        return $this->suket_berlaku->lte(now()->addDays(30)) ? 'segera' : 'layak';
    }

    public function bolehDipakai(): bool
    {
        return in_array($this->statusEfektif(), ['layak', 'segera'], true);
    }

    /** Angka yang menimpa katalog di simulasi. */
    public function spek(): array
    {
        return ['kapasitas_t' => $this->kapasitas_t, 'berat_t' => $this->berat_crane_t,
                'boom_m' => $this->boom_maks_m, 'hook_t' => $this->berat_hook_t];
    }

    public function labelJenis(): string
    {
        return Katalog::ALAT[$this->jenis]['label'] ?? $this->jenis;
    }
}
