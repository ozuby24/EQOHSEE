<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Pekerja;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu peristiwa absensi — pindaian, ketukan, atau catatan tangan.
 *
 * HANYA DITAMBAH, TIDAK PERNAH DISUNTING. Inilah buktinya: koreksi apa
 * pun terjadi pada catatan harian, bukan di sini. Pertanyaan pertama
 * auditor adalah "jam berapa alatnya mencatat orang ini", dan
 * jawabannya harus tetap ada sesudah jamnya dikoreksi manusia.
 *
 * `terjadi` DAN `diterima` KEDUANYA DISIMPAN, dan keduanya berbeda
 * berjam-jam pada pengiriman luring. Yang menentukan hari kerja adalah
 * yang pertama; yang menjelaskan mengapa datanya baru muncul kemarin
 * adalah yang kedua.
 */
#[ScopedBy(MilikPerusahaan::class)]
class AbsensiJejak extends Model
{
    use BerpemilikPerusahaan;

    public const ARAH   = ['masuk' => 'Masuk', 'keluar' => 'Keluar'];
    public const SUMBER = ['mesin' => 'Mesin', 'ponsel' => 'Ponsel', 'manual' => 'Manual'];

    protected $table = 'hr_absensi_jejak';

    protected $guarded = ['id'];

    protected $attributes = ['sumber' => 'mesin', 'luring' => false];

    protected function casts(): array
    {
        return [
            'terjadi'     => 'datetime',
            'diterima'    => 'datetime',
            'lat'         => 'float',
            'lng'         => 'float',
            'jarak_m'     => 'integer',
            'dalam_area'  => 'boolean',
            'luring'      => 'boolean',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function pekerja(): BelongsTo { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }
    public function mesin(): BelongsTo   { return $this->belongsTo(MesinAbsensi::class, 'mesin_id'); }

    public function scopePada(Builder $q, string $tanggal): Builder
    {
        /* Batas atasnya sampai akhir hari. `terjadi` bertipe TIMESTAMP,
           jadi membandingkannya dengan untaian tanggal saja akan
           melewatkan seluruh peristiwa sesudah tengah malam — yaitu
           seluruhnya. */
        return $q->where('terjadi', '>=', $tanggal.' 00:00:00')
            ->where('terjadi', '<=', $tanggal.' 23:59:59');
    }
}
