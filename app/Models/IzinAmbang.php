<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Izin;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ambang gas yang berlaku di situs ini.
 *
 * Menimpa ambang bawaan. Yang mengikat adalah prosedur perusahaan dan
 * ketentuan yang berlaku di wilayahnya, bukan angka yang ditanam di
 * dalam aplikasi — dan selama belum diisi, pemakaian bawaan itu
 * dinyatakan, bukan disembunyikan.
 */
#[ScopedBy(MilikPerusahaan::class)]
class IzinAmbang extends Model
{
    use BerpemilikPerusahaan;

    protected $fillable = ['company_id', 'parameter', 'batas_min', 'batas_maks', 'satuan', 'acuan'];

    protected function casts(): array
    {
        return ['batas_min' => 'float', 'batas_maks' => 'float'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    /**
     * Ambang berlaku: bawaan yang ditimpa oleh yang ditetapkan situs.
     *
     * @param  \Illuminate\Support\Collection<int,IzinAmbang> $tersimpan
     * @return array<string,array{min:?float,maks:?float,satuan:string,nama:string,ditetapkan:bool,acuan:?string}>
     */
    public static function berlaku($tersimpan): array
    {
        $ambang = [];

        foreach (Izin::ambangBawaan() as $kode => $a) {
            $punya = $tersimpan->firstWhere('parameter', $kode);

            $ambang[$kode] = [
                'min'        => $punya ? $punya->batas_min : $a['min'],
                'maks'       => $punya ? $punya->batas_maks : $a['maks'],
                'satuan'     => $punya?->satuan ?: $a['satuan'],
                'nama'       => $a['nama'],
                'ditetapkan' => (bool) $punya,
                'acuan'      => $punya?->acuan,
            ];
        }

        return $ambang;
    }

    public function toView(): array
    {
        return [
            'id' => $this->id, 'parameter' => $this->parameter,
            'min' => $this->batas_min, 'maks' => $this->batas_maks,
            'satuan' => $this->satuan, 'acuan' => $this->acuan,
        ];
    }
}
