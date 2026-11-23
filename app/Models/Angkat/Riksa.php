<?php

namespace App\Models\Angkat;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Laporan riksa uji pesawat angkat / alat bantu angkat. Riwayat — tidak pernah diubah. */
#[ScopedBy(MilikPerusahaan::class)]
class Riksa extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'angkat_riksa';

    protected $fillable = [
        'company_id', 'user_id', 'alat_id', 'gear_id', 'nomor', 'objek', 'kategori_uji', 'jenis',
        'pemeriksa', 'skp_nomor', 'pjk3', 'swl_t', 'uji_statis_pct', 'beban_statis_t', 'uji_dinamis_pct',
        'beban_dinamis_t', 'tahan_menit', 'ukur', 'ndt_metode', 'ndt_hasil', 'butir', 'putusan', 'alasan',
        'kode_warna', 'diperiksa_at', 'berlaku_sampai',
    ];

    protected function casts(): array
    {
        return [
            'swl_t' => 'float', 'beban_statis_t' => 'float', 'beban_dinamis_t' => 'float',
            'uji_statis_pct' => 'integer', 'uji_dinamis_pct' => 'integer', 'tahan_menit' => 'integer',
            'ukur' => 'array', 'butir' => 'array', 'diperiksa_at' => 'date', 'berlaku_sampai' => 'date',
        ];
    }

    public function alat(): BelongsTo { return $this->belongsTo(Alat::class, 'alat_id'); }
    public function gear(): BelongsTo { return $this->belongsTo(Gear::class, 'gear_id'); }
    public function pencatat(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
