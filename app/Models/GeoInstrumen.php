<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Alat pantau yang terpasang pada sebuah lereng.
 *
 * Tidak memakai registri Keselamatan Operasi seperti pompa penirisan.
 * Prisma dan piezometer bukan alat yang dioperasikan dan dirawat menurut
 * jadwal kelaikan; yang menentukan kelayakannya adalah kalibrasi, dan
 * kegagalannya tidak melukai siapa pun secara langsung — ia hanya
 * membuat lereng berhenti terpantau, yang bahayanya justru karena tidak
 * terlihat.
 */
class GeoInstrumen extends Model
{
    public const JENIS  = ['prisma', 'extensometer', 'piezometer', 'inklinometer', 'radar'];
    public const STATUS = ['siap', 'rusak', 'perawatan', 'arsip'];

    protected $fillable = [
        'geo_lereng_id', 'kode', 'jenis', 'status', 'elevasi_m', 'kalibrasi_terakhir',
    ];

    protected function casts(): array
    {
        return [
            'kalibrasi_terakhir' => 'date',
            'elevasi_m'          => 'float',
        ];
    }

    public function lereng(): BelongsTo { return $this->belongsTo(GeoLereng::class, 'geo_lereng_id'); }
    public function bacaan(): HasMany   { return $this->hasMany(GeoBacaan::class); }

    /** Alat yang tidak siap berarti lerengnya tidak sepenuhnya terpantau. */
    public function memantau(): bool
    {
        return $this->status === 'siap';
    }

    public function toView(): array
    {
        return [
            'id'         => $this->id,
            'kode'       => $this->kode,
            'jenis'      => $this->jenis,
            'status'     => $this->status,
            'elevasi_m'  => $this->elevasi_m,
            'kalibrasi'  => $this->kalibrasi_terakhir?->toDateString(),
            'memantau'   => $this->memantau(),
        ];
    }
}
