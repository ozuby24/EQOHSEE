<?php

namespace App\Models\Miners;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Surat rujukan ke dokter spesialis atas sebuah hasil MCU.
 *
 * Berkasnya TERTUTUP — lihat App\Support\Berkas. Isinya menyebut ke poli
 * mana orangnya dirujuk beserta diagnosis awalnya, dan itu rincian medis
 * yang punya aturan kerahasiaannya sendiri.
 */
class McuRujukan extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'orang';

    protected $table = 'mnr_mcu_rujukan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['tanggal_surat' => 'date'];
    }

    public function orang(): BelongsTo
    {
        return $this->belongsTo(McuOrang::class, 'mcu_orang_id');
    }
}
