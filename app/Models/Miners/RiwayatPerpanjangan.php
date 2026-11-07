<?php

namespace App\Models\Miners;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak satu perpanjangan Mine Permit atau SIMPER — `riwayat_perpanjangans`
 * Safe Track: tanggal habis lama dan baru, beserta salinan data sebelum
 * dan sesudahnya.
 *
 * Dibaca selalu MELALUI dokumennya (permit/SIMPER yang sudah tersaring
 * perusahaannya), karena itu tidak memakai scope perusahaan sendiri.
 */
class RiwayatPerpanjangan extends Model
{
    public const JENIS = ['permit' => 'Mine Permit', 'simper' => 'SIMPER'];

    protected $table = 'mnr_riwayat_perpanjangan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'berlaku_lama' => 'date',
            'berlaku_baru' => 'date',
            'data_lama'    => 'array',
            'data_baru'    => 'array',
            'urutan'       => 'integer',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
