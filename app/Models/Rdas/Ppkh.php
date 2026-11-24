<?php

namespace App\Models\Rdas;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/**
 * Satu persetujuan penggunaan kawasan hutan dan kewajiban Rehab DAS-nya.
 * Berakhirnya PPKH TIDAK membebaskan pemegangnya dari utang kewajiban
 * penanaman — karena itu baris ini tidak pernah "selesai" karena tanggal,
 * hanya karena seluruh luas kewajiban diserahterimakan.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Ppkh extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'rdas_ppkh';

    protected $fillable = ['company_id', 'user_id', 'kode', 'nomor_sk', 'tanggal_sk', 'jangka_tahun', 'berlaku_sampai', 'tujuan', 'kegiatan', 'provinsi', 'kabupaten', 'bpdas', 'luas_ppkh_ha', 'nomor_pak', 'luas_pak_ha', 'rasio', 'kewajiban_ha', 'batas_selesai', 'status_lokasi', 'rurh_status', 'luas_terganggu_ha', 'luas_direklamasi_ha', 'nilai_reklamasi', 'catatan'];

    protected function casts(): array
    {
        return ['tanggal_sk' => 'date', 'berlaku_sampai' => 'date', 'batas_selesai' => 'date', 'jangka_tahun' => 'integer', 'luas_ppkh_ha' => 'float', 'luas_pak_ha' => 'float', 'rasio' => 'float', 'kewajiban_ha' => 'float', 'luas_terganggu_ha' => 'float', 'luas_direklamasi_ha' => 'float', 'nilai_reklamasi' => 'float'];
    }

    public function blok(): HasMany { return $this->hasMany(Blok::class, 'ppkh_id'); }
    public function laporan(): HasMany { return $this->hasMany(Laporan::class, 'ppkh_id'); }
    public function kejadian(): HasMany { return $this->hasMany(Kejadian::class, 'ppkh_id'); }

    /** Sisa hari sampai batas penyelesaian kewajiban (negatif = lewat). */
    public function sisaHari(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->batas_selesai, false);
    }

    /** Predikat nilai reklamasi hutan: baik ≥ 80, sedang 60–79, buruk < 60. */
    public function predikatReklamasi(): ?string
    {
        if ($this->nilai_reklamasi === null) return null;
        return $this->nilai_reklamasi >= 80 ? 'baik' : ($this->nilai_reklamasi >= 60 ? 'sedang' : 'buruk');
    }
}
