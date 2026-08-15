<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy(MilikPerusahaan::class)]
class MineOperationalRecord extends Model
{
    use BerpemilikPerusahaan;
    use Ditinjau;

    /**
     * `status` sengaja tidak ada di sini. Ia hanya berpindah lewat
     * Ditinjau::ajukan/setujui/tolak, sehingga tidak ada jalan bagi
     * pengirim data untuk menyebut sendiri datanya sudah disetujui.
     */
    protected $fillable = [
        'company_id', 'user_id', 'tanggal', 'shift', 'pit', 'area', 'material',
        'produksi_ton', 'overburden_bcm', 'jarak_angkut_km', 'jumlah_truk',
        'jumlah_excavator', 'jam_operasi', 'jam_delay', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'produksi_ton' => 'float',
            'overburden_bcm' => 'float',
            'jarak_angkut_km' => 'float',
            'jumlah_truk' => 'integer',
            'jumlah_excavator' => 'integer',
            'jam_operasi' => 'float',
            'jam_delay' => 'float',
            'diajukan_pada' => 'datetime',
            'ditinjau_pada' => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
