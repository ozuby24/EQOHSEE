<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hasil satu peledakan.
 *
 * Misfire dan flyrock dicatat di sini, tetapi peringatannya TIDAK
 * menunggu tinjauan — sama seperti gejala lapangan pada modul kestabilan
 * lereng, dan karena alasan yang sama. Bahan peledak yang gagal meledak
 * tertinggal di dalam tumpukan material, dan alat gali berikutnya yang
 * akan menemukannya. Menahan tanda bahaya sampai ada yang sempat
 * menyetujuinya adalah kekeliruan yang tidak dapat diperbaiki setelahnya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class LedakHasil extends Model
{
    use BerpemilikPerusahaan;
    use Ditinjau;

    protected $fillable = [
        'company_id', 'user_id', 'ledak_rencana_id', 'waktu_ledak', 'volume_bcm',
        'ada_misfire', 'misfire_lubang', 'ada_flyrock', 'flyrock_jarak_m',
        'backbreak_m', 'bongkah_persen', 'kejadian', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'waktu_ledak'     => 'datetime',
            'volume_bcm'      => 'float',
            'ada_misfire'     => 'boolean',
            'ada_flyrock'     => 'boolean',
            'flyrock_jarak_m' => 'float',
            'backbreak_m'     => 'float',
            'bongkah_persen'  => 'float',
            'diajukan_pada'   => 'datetime',
            'ditinjau_pada'   => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function rencana(): BelongsTo { return $this->belongsTo(LedakRencana::class, 'ledak_rencana_id'); }

    public function toView(): array
    {
        return [
            'id'              => $this->id,
            'waktu'           => $this->waktu_ledak?->toDateTimeString(),
            'waktuLabel'      => $this->waktu_ledak?->format('d M Y H:i'),
            'volume_bcm'      => $this->volume_bcm,
            'ada_misfire'     => $this->ada_misfire,
            'misfire_lubang'  => $this->misfire_lubang,
            'ada_flyrock'     => $this->ada_flyrock,
            'flyrock_jarak_m' => $this->flyrock_jarak_m,
            'backbreak_m'     => $this->backbreak_m,
            'bongkah_persen'  => $this->bongkah_persen,
            'kejadian'        => $this->kejadian,
            'status'          => $this->status,
            'statusLabel'     => \App\Support\Alur::LABEL[$this->status] ?? $this->status,
            'alur' => [
                'dapatDiubah'   => $this->dapatDiubah(),
                'dapatDiajukan' => $this->dapatDiubah(),
                'dapatDitinjau' => $this->dapatDitinjauOleh(auth()->user()),
                'alasanTolak'   => $this->alasan_tolak,
            ],
        ];
    }
}
