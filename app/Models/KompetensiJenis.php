<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * Master jenis kompetensi — 51 butir menurut SK Dirjen 185.K/2019.
 *
 * Baris ber-company_id NULL adalah milik bersama seluruh pemasangan;
 * perusahaan yang perlu menambah jenisnya sendiri membuat baris
 * ber-company_id. Pola yang sama dipakai kursus LMS dan template
 * inspeksi, jadi scope-nya sudah memperlakukan NULL sebagai "terlihat
 * oleh semua".
 */
#[ScopedBy(MilikPerusahaan::class)]
class KompetensiJenis extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'kompetensi_jenis';

    protected $fillable = [
        'company_id', 'nama', 'lembaga', 'klasifikasi', 'berlaku_bulan', 'aktif', 'urutan',
    ];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function sertifikat()
    {
        return $this->hasMany(PasporSertifikat::class, 'kompetensi_jenis_id');
    }

    public function scopeAktif($q)
    {
        return $q->where('aktif', true);
    }
}
