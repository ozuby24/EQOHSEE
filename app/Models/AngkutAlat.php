<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu unit armada angkut: truk atau alat muat.
 *
 * Berdiri terpisah dari registri Keselamatan Operasi karena yang
 * dibutuhkan di sini tidak ada di sana — kapasitas nominal truk dan
 * kapasitas mangkuk alat muat. Tautan `ko_object_id` disimpan supaya
 * satu unit tetap dapat ditelusuri ke berkas kelayakan dan jadwal
 * perawatannya, tanpa memaksa kedua modul memakai satu tabel yang
 * separuh kolomnya selalu kosong.
 */
#[ScopedBy(MilikPerusahaan::class)]
class AngkutAlat extends Model
{
    use BerpemilikPerusahaan;

    public const KELAS = ['truk', 'alat-muat'];

    protected $fillable = [
        'company_id', 'ko_object_id', 'kode', 'nama', 'kelas', 'tipe',
        'kapasitas_ton', 'kapasitas_bucket_m3', 'faktor_isi', 'aktif', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'kapasitas_ton'       => 'float',
            'kapasitas_bucket_m3' => 'float',
            'faktor_isi'          => 'float',
            'aktif'               => 'boolean',
        ];
    }

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function koObject(): BelongsTo { return $this->belongsTo(KoObject::class, 'ko_object_id'); }
    public function muatan(): HasMany     { return $this->hasMany(AngkutMuatan::class, 'angkut_alat_id'); }

    public function truk(): bool { return $this->kelas === 'truk'; }

    /**
     * Kapasitas nominal sudah terisi.
     *
     * Tanpa angka ini muatan tetap tercatat, tetapi tidak ada pembagi
     * bagi kaidah 10/10/20 — sehingga muatan berlebih tidak akan pernah
     * ketahuan, dan tidak ada galat apa pun yang menandainya.
     */
    public function kapasitasDitetapkan(): bool
    {
        return $this->kapasitas_ton !== null && $this->kapasitas_ton > 0;
    }

    public function toView(): array
    {
        return [
            'id'       => $this->id,
            'kode'     => $this->kode,
            'nama'     => $this->nama,
            'kelas'    => $this->kelas,
            'tipe'     => $this->tipe,
            'kapasitas'=> $this->kapasitas_ton,
            'kapasitasDitetapkan' => $this->kapasitasDitetapkan(),
            'bucket'   => $this->kapasitas_bucket_m3,
            'faktorIsi'=> $this->faktor_isi,
            'aktif'    => $this->aktif,
            'catatan'  => $this->catatan,
        ];
    }
}
