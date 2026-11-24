<?php

namespace App\Models\Rdas;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/** Permohonan penilaian keberhasilan dan serah terima (boleh bertahap). */
#[ScopedBy(MilikPerusahaan::class)]
class Penilaian extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'rdas_penilaian';

    protected $fillable = ['company_id', 'blok_id', 'kode', 'tahap', 'luas_diajukan_ha', 'luas_berhasil_ha', 'status', 'diajukan_at', 'sk_tim_nomor', 'sk_tim_at', 'jadwal_mulai', 'jadwal_selesai', 'ketua_tim', 'persen_tumbuh', 'sehat_ha', 'prasyarat', 'bast_nomor', 'bast_at', 'catatan'];

    protected function casts(): array
    {
        return ['tahap' => 'integer', 'luas_diajukan_ha' => 'float', 'luas_berhasil_ha' => 'float', 'diajukan_at' => 'date', 'sk_tim_at' => 'date', 'jadwal_mulai' => 'date', 'jadwal_selesai' => 'date', 'persen_tumbuh' => 'float', 'sehat_ha' => 'float', 'prasyarat' => 'array', 'bast_at' => 'date'];
    }

    /** Status yang masih menunggu keputusan. */
    public const AKTIF = ['diajukan', 'tim_dibentuk', 'dinilai', 'berhasil'];

    public function blok(): BelongsTo { return $this->belongsTo(Blok::class, 'blok_id'); }
}
