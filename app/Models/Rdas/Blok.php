<?php

namespace App\Models\Rdas;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/**
 * Satu lokasi Rehab DAS yang ditetapkan (SK Penlok) dengan RKP-nya.
 * `fase` disimpan supaya peta dan daftar dapat disaring, tetapi selalu
 * diturunkan dari tanggal tanam dan status penilaian lewat
 * Kepatuhan::faseBlok() — bukan dipilih orang.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Blok extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'rdas_blok';

    protected $fillable = ['company_id', 'ppkh_id', 'kode', 'nama', 'sk_penlok_nomor', 'sk_penlok_tanggal', 'das', 'sub_das', 'kabupaten', 'kecamatan', 'desa', 'fungsi_kawasan', 'pemangku', 'ekosistem', 'luas_ha', 'jarak_tanam', 'kerapatan', 'komposisi', 'rkp_nomor', 'rkp_disahkan_at', 'tanggal_tanam', 'kontraktor', 'anggaran_rp', 'fase', 'lat', 'lng', 'geotag_total', 'geotag_duplikat', 'catatan'];

    protected function casts(): array
    {
        return ['sk_penlok_tanggal' => 'date', 'rkp_disahkan_at' => 'date', 'tanggal_tanam' => 'date', 'komposisi' => 'array', 'luas_ha' => 'float', 'kerapatan' => 'integer', 'anggaran_rp' => 'float', 'lat' => 'float', 'lng' => 'float', 'geotag_total' => 'integer', 'geotag_duplikat' => 'integer'];
    }

    public function ppkh(): BelongsTo { return $this->belongsTo(Ppkh::class, 'ppkh_id'); }
    public function petak(): HasMany { return $this->hasMany(Petak::class, 'blok_id'); }
    public function kegiatan(): HasMany { return $this->hasMany(Kegiatan::class, 'blok_id'); }
    public function pantau(): HasMany { return $this->hasMany(Pantau::class, 'blok_id'); }
    public function penilaian(): HasMany { return $this->hasMany(Penilaian::class, 'blok_id'); }
    public function penilaianTerakhir(): HasOne { return $this->hasOne(Penilaian::class, 'blok_id')->latestOfMany(); }

    /** Umur tanaman dalam bulan sejak P0. */
    public function usiaBulan(): ?int
    {
        return $this->tanggal_tanam ? (int) floor($this->tanggal_tanam->diffInMonths(now(), false)) : null;
    }
}
