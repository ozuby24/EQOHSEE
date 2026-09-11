<?php

namespace App\Models\Hr;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\Miners\Pekerja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Keanggotaan regu, BERTANGGAL.
 *
 * Seorang pindah regu, dan riwayatnya harus tetap terbaca: roster
 * Januari disusun ketika ia masih di Regu A. Ditulis sebagai satu kolom
 * pada pekerjanya, roster Januari ikut berpindah begitu ia dipindah
 * pada bulan Maret — jadwal yang sudah berlalu berubah sendiri, dan
 * rekonsiliasi absensinya tidak lagi cocok dengan apa pun.
 */
class ReguAnggota extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'regu';

    protected $table = 'hr_regu_anggota';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['mulai' => 'date', 'selesai' => 'date'];
    }

    public function regu(): BelongsTo    { return $this->belongsTo(Regu::class, 'regu_id'); }
    public function pekerja(): BelongsTo { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }
}
