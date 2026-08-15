<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pompa yang melayani sebuah kolam.
 *
 * Barisnya hanya penghubung: data alatnya tetap di registri Keselamatan
 * Operasi, sehingga jadwal perawatan dan perintah kerjanya terbaca oleh
 * modul Pemeliharaan tanpa penyalinan.
 */
class WaterSumpPump extends Model
{
    use BerindukPerusahaan;

    /** Pemiliknya ada pada induknya; lihat BerindukPerusahaan. */
    protected static string $indukPerusahaan = 'sump';

    public const STATUS = ['siap', 'jalan', 'rusak', 'perawatan'];

    protected $fillable = [
        'water_sump_id', 'ko_object_id', 'nama', 'kapasitas_m3_jam', 'status',
    ];

    protected function casts(): array
    {
        return ['kapasitas_m3_jam' => 'float'];
    }

    public function sump(): BelongsTo  { return $this->belongsTo(WaterSump::class, 'water_sump_id'); }
    public function objek(): BelongsTo { return $this->belongsTo(KoObject::class, 'ko_object_id'); }

    public function label(): string
    {
        return $this->objek?->kode ?: ($this->nama ?: "Pompa #{$this->id}");
    }
}
