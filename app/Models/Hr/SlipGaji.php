<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Pekerja;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Slip gaji satu orang pada satu periode.
 *
 * SELURUH KOMPONENNYA DISIMPAN TERPISAH, bukan hanya jumlah akhirnya.
 * Slip gaji adalah dokumen yang dipersengketakan, dan sengketanya
 * selalu tentang satu komponen — bukan tentang jumlahnya. Disimpan
 * sebagai satu angka, yang menjawab harus menghitung ulang dengan
 * aturan yang mungkin sudah berubah.
 */
#[ScopedBy(MilikPerusahaan::class)]
class SlipGaji extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'pay_slip';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'pokok'                 => 'float',
            'tunjangan_tetap'       => 'float',
            'tunjangan_tidak_tetap' => 'float',
            'tunjangan_site'        => 'float',
            'hari_site'             => 'integer',
            'lembur'                => 'float',
            'lembur_jam'            => 'float',
            'bpjs_perusahaan'       => 'float',
            'bruto'                 => 'float',
            'bpjs_karyawan'         => 'float',
            'pph21'                 => 'float',
            'potongan_lain'         => 'float',
            'neto'                  => 'float',
            'ter_tarif'             => 'float',
            'rincian'               => 'array',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function periode(): BelongsTo { return $this->belongsTo(PeriodeGaji::class, 'periode_id'); }
    public function pekerja(): BelongsTo { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }

    /** Seluruh potongan yang mengurangi yang dibawa pulang. */
    public function potongan(): float
    {
        return round($this->bpjs_karyawan + $this->pph21 + $this->potongan_lain, 2);
    }
}
