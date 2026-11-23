<?php

namespace App\Models\Perancah;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/** Hasil kalkulator BoM & beban — satu per unit, ditimpa saat spesifikasi berubah. */
#[ScopedBy(MilikPerusahaan::class)]
class Hitung extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'perancah_hitung';

    protected $fillable = [
        'company_id', 'unit_id', 'bay', 'lift', 'standard', 'ledger', 'transom', 'bracing', 'plank',
        'base_plate', 'coupler', 'tangga', 'ikatan_minimum', 'luas_dek_m2', 'beban_mati_kg', 'beban_hidup_kg',
        'beban_kaki_kn', 'faktor_keamanan', 'rasio_hb', 'tekanan_tanah_kn_m2', 'panjang_pipa_m', 'bom',
    ];

    protected function casts(): array
    {
        return [
            'bom' => 'array',
            'luas_dek_m2' => 'float', 'beban_mati_kg' => 'float', 'beban_hidup_kg' => 'float',
            'beban_kaki_kn' => 'float', 'faktor_keamanan' => 'float', 'rasio_hb' => 'float',
            'tekanan_tanah_kn_m2' => 'float', 'panjang_pipa_m' => 'float',
        ];
    }
}
