<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Daftar acuan jenis unit SPIP.
 *
 * DIPELIHARA PEMAKAINYA, bukan daftar tetap dari peraturan. Itu bukan
 * kelalaian melainkan temuan dari data D'Best: master_unitkelayakans di
 * sana berisi jenis unit yang benar-benar dipakai perusahaannya, dan
 * setiap tambang punya susunan alat yang berbeda. Daftar tetap bawaan
 * sistem akan selalu kurang bagi sebagian dan berlebih bagi sebagian
 * lain, dan yang kurang akan diakali dengan mengetik bebas lagi —
 * mengembalikan persis masalah yang hendak dihilangkan.
 *
 * Gunanya menyeragamkan penulisan. Tanpa daftar ini, "Dump Truck",
 * "Dumptruck", dan "DT" menjadi tiga jenis berbeda di mata sistem:
 * rekap per jenis tidak dapat dipercaya dan penyaringan selalu
 * kehilangan sebagian barisnya — tanpa galat, hanya angka yang salah.
 */
#[ScopedBy(MilikPerusahaan::class)]
class KoUnitMaster extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'ko_unit_master';

    protected $fillable = [
        'company_id', 'kode', 'unit', 'kategori',
        'interval_tahun', 'keterangan', 'aktif', 'urutan',
    ];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'interval_tahun' => 'integer', 'urutan' => 'integer'];
    }

    public function company() { return $this->belongsTo(Company::class); }
    public function objek()   { return $this->hasMany(KoObject::class, 'ko_unit_master_id'); }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }
}
