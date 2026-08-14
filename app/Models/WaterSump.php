<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\NeracaAir;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Kolam penirisan: sump, kolam pengendap, settling pond.
 *
 * Pompanya menunjuk registri Keselamatan Operasi lewat WaterSumpPump;
 * pompa penirisan adalah alat, dan kegagalannya menenggelamkan pit —
 * justru termasuk yang paling perlu ikut terbaca oleh modul
 * Pemeliharaan.
 */
#[ScopedBy(MilikPerusahaan::class)]
class WaterSump extends Model
{
    use BerpemilikPerusahaan;

    public const JENIS = ['sump', 'sediment_pond', 'settling_pond'];
    public const STATUS = ['aktif', 'arsip'];

    protected $fillable = [
        'company_id', 'user_id', 'kode', 'nama', 'jenis', 'lokasi',
        'kapasitas_m3', 'luas_tangkapan_ha', 'koefisien_limpasan',
        'elevasi_luapan_m', 'status', 'pembersihan_terakhir',
        'interval_bersih_hari', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'kapasitas_m3'         => 'float',
            'luas_tangkapan_ha'    => 'float',
            'koefisien_limpasan'   => 'float',
            'elevasi_luapan_m'     => 'float',
            'pembersihan_terakhir' => 'date',
            'interval_bersih_hari' => 'integer',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function pumps(): HasMany     { return $this->hasMany(WaterSumpPump::class); }
    public function logs(): HasMany      { return $this->hasMany(WaterLog::class)->latest('tanggal'); }

    /** Catatan terakhir; dasar volume sekarang. */
    public function catatanTerakhir(): ?WaterLog
    {
        return $this->relationLoaded('logs')
            ? $this->logs->first()
            : $this->logs()->first();
    }

    public function volumeSekarang(): float
    {
        return (float) ($this->catatanTerakhir()?->volume_m3 ?? 0);
    }

    public function neraca(): NeracaAir
    {
        return new NeracaAir(
            $this->kapasitas_m3,
            $this->volumeSekarang(),
            $this->luas_tangkapan_ha,
            $this->koefisien_limpasan ?: NeracaAir::KOEFISIEN_BAWAAN,
        );
    }

    /** Jumlah kapasitas pompa yang benar-benar siap jalan. */
    public function kapasitasPompaSiap(): float
    {
        return (float) $this->pumps
            ->whereIn('status', ['siap', 'jalan'])
            ->sum('kapasitas_m3_jam');
    }

    /**
     * Kapasitas seluruh pompa terpasang, termasuk yang sedang rusak.
     *
     * Dibedakan dari yang siap supaya selisihnya terbaca: kolam yang
     * kapasitas terpasangnya cukup tetapi separuh pompanya rusak
     * menghadapi risiko yang sama dengan kolam yang pompanya memang
     * kurang, dan hanya satu dari keduanya yang diperbaiki dengan
     * membeli pompa baru.
     */
    public function kapasitasPompaTerpasang(): float
    {
        return (float) $this->pumps->sum('kapasitas_m3_jam');
    }

    public function pompaRusak(): int
    {
        return $this->pumps->where('status', 'rusak')->count();
    }

    /** Hari sampai jadwal pembersihan berikutnya; null bila tak berjadwal. */
    public function sisaHariBersih(): ?int
    {
        if (!$this->pembersihan_terakhir || !$this->interval_bersih_hari) return null;

        return (int) round(
            now()->startOfDay()->diffInDays(
                $this->pembersihan_terakhir->copy()->addDays($this->interval_bersih_hari)->startOfDay(),
                false
            )
        );
    }

    public function toView(): array
    {
        $n = $this->neraca();
        $siap = $this->kapasitasPompaSiap();

        return [
            'id'                => $this->id,
            'kode'              => $this->kode,
            'nama'              => $this->nama,
            'jenis'             => $this->jenis,
            'lokasi'            => $this->lokasi,
            'status'            => $this->status,
            'kapasitas_m3'      => $this->kapasitas_m3,
            'luas_tangkapan_ha' => $this->luas_tangkapan_ha,
            'koefisien'         => $this->koefisien_limpasan,
            'volume'            => $this->volumeSekarang(),
            'terisiPersen'      => round($n->terisiPersen(), 1),
            'ruangKosong'       => round($n->ruangKosong(), 2),
            'hujanTertampung'   => $n->hujanTertampungMm(),
            'pompaSiap'         => $siap,
            'pompaTerpasang'    => $this->kapasitasPompaTerpasang(),
            'pompaRusak'        => $this->pompaRusak(),
            'sisaHariBersih'    => $this->sisaHariBersih(),
            'perusahaan'        => $this->company?->name,
        ];
    }
}
