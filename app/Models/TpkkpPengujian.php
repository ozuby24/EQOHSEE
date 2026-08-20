<?php

namespace App\Models;

use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu sesi pengujian (metode PJ) yang sudah selesai dikerjakan.
 *
 * Nilai perorangan TIDAK ditampilkan kepada pesertanya — yang dipakai
 * penilaian adalah rerata tingkat seluruh peserta. Barisnya tetap
 * disimpan lengkap karena admin memerlukannya untuk melihat sebaran,
 * dan `pindah_layar` untuk melihat siapa yang mengerjakannya sambil
 * membuka jendela lain.
 */
#[ScopedBy(MilikPerusahaan::class)]
class TpkkpPengujian extends Model
{
    protected $table = 'tpkkp_pengujian';

    protected $fillable = [
        'company_id', 'ext_id',
        'nama', 'nrp', 'jabatan', 'dept', 'perusahaan', 'kunci_identitas',
        'benar', 'total', 'skor_pct', 'tingkat',
        'durasi_detik', 'pindah_layar', 'mulai', 'ts',
    ];

    protected function casts(): array
    {
        return [
            'benar'        => 'int',
            'total'        => 'int',
            'skor_pct'     => 'float',
            'tingkat'      => 'int',
            'durasi_detik' => 'int',
            'pindah_layar' => 'int',
            'mulai'        => 'datetime',
            'ts'           => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
