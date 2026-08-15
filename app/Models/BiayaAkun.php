<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu akun biaya pada bagan akun.
 *
 * Berdiri sebagai data karena susunannya mengikuti RKAB dan kebiasaan
 * tiap perusahaan, dan berganti mengikuti tahun anggarannya. Daftar
 * yang ditanam di kode menuntut penerbitan aplikasi baru setiap kali
 * satu baris anggaran berubah namanya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class BiayaAkun extends Model
{
    use BerpemilikPerusahaan;

    public const JENIS = ['tetap', 'variabel'];

    /**
     * Kelompok bawaan, bukan daftar tertutup.
     *
     * Dipakai untuk mengelompokkan tampilan dan menyarankan pilihan;
     * perusahaan yang memakai istilah lain tetap dapat mengisikannya.
     */
    public const KELOMPOK = [
        'bahan-bakar', 'pelumas', 'ban', 'suku-cadang', 'bahan-peledak',
        'upah', 'sewa-alat', 'kontraktor', 'listrik', 'lain',
    ];

    protected $fillable = [
        'company_id', 'kode', 'nama', 'kelompok', 'jenis', 'satuan', 'aktif', 'catatan',
    ];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function anggaran(): HasMany    { return $this->hasMany(BiayaAnggaran::class, 'biaya_akun_id'); }
    public function realisasi(): HasMany   { return $this->hasMany(BiayaRealisasi::class, 'biaya_akun_id'); }

    /** Akun bersatuan dapat dipecah selisihnya menjadi harga dan pemakaian. */
    public function bersatuan(): bool
    {
        return trim((string) $this->satuan) !== '';
    }

    public function toView(): array
    {
        return [
            'id'        => $this->id,
            'kode'      => $this->kode,
            'nama'      => $this->nama,
            'kelompok'  => $this->kelompok,
            'jenis'     => $this->jenis,
            'satuan'    => $this->satuan,
            'bersatuan' => $this->bersatuan(),
            'aktif'     => $this->aktif,
            'catatan'   => $this->catatan,
        ];
    }
}
