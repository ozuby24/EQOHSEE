<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;

/**
 * Jatah cuti satu orang untuk satu tahun.
 *
 * Bawaan tahun lalu dipisah dari jatah tahun ini. Keduanya sama-sama
 * dapat dipakai, tetapi aturan hangusnya berbeda — dan begitu keduanya
 * disatukan menjadi satu angka, tidak ada lagi cara mengetahui berapa
 * yang akan hangus akhir tahun tanpa menghitung mundur dari catatan
 * lama.
 */
class MinersCutiJatah extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'paspor';

    protected $table = 'miners_cuti_jatah';

    protected $fillable = ['paspor_id', 'tahun', 'jatah', 'bawaan', 'catatan'];

    protected function casts(): array
    {
        return ['tahun' => 'integer', 'jatah' => 'integer', 'bawaan' => 'integer'];
    }

    /** Jatah baku bila belum pernah diatur — dua belas hari kerja setahun. */
    public const BAKU = 12;

    public function paspor() { return $this->belongsTo(Paspor::class); }

    public function total(): int
    {
        return $this->jatah + $this->bawaan;
    }
}
