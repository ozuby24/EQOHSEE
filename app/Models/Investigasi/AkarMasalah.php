<?php

namespace App\Models\Investigasi;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu akar masalah.
 *
 * `taksonomi_id` boleh kosong: sebagian akar masalah lahir dari 5 Why
 * dan tidak punya padanan di kamus mana pun. Yang tidak boleh adalah
 * akar masalah TANPA BUKTI — itu pendapat, dan pendapat tidak bertahan
 * di depan Inspektur Tambang.
 */
class AkarMasalah extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'investigasi';

    protected $table = 'inv_akar_masalah';

    protected $fillable = ['investigasi_id', 'uraian', 'metode', 'taksonomi_id', 'urutan'];

    public function investigasi() { return $this->belongsTo(Investigasi::class, 'investigasi_id'); }
    public function taksonomi()   { return $this->belongsTo(Taksonomi::class, 'taksonomi_id'); }

    public function bukti()
    {
        return $this->belongsToMany(Bukti::class, 'inv_akar_bukti', 'akar_id', 'bukti_id');
    }

    /** Akar tanpa bukti — ditandai di layar, bukan disembunyikan. */
    public function disokongBukti(): bool
    {
        return $this->relationLoaded('bukti')
            ? $this->bukti->isNotEmpty()
            : $this->bukti()->exists();
    }
}
