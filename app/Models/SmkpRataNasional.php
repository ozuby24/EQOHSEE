<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Rata-rata nasional FR, SR, ASR, MFR satu tahun Surat Edaran (port DBEST
 * #23). Isian administrator menimpa/menambah bawaan
 * SmkpTahap::RATA_NASIONAL — lihat SmkpTahap::daftarRataNasional().
 * Acuan nasional: tidak bercakupan perusahaan.
 */
class SmkpRataNasional extends Model
{
    protected $table = 'smkp_rata_nasional';

    protected $fillable = ['tahun', 'nomor', 'tanggal', 'fr', 'sr', 'asr', 'mfr', 'catatan', 'user_id'];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer', 'user_id' => 'integer', 'tanggal' => 'date',
            'fr' => 'float', 'sr' => 'float', 'asr' => 'float', 'mfr' => 'float',
        ];
    }
}
