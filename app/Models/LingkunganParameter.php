<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Baku mutu satu parameter lingkungan.
 *
 * Berdiri sebagai data, bukan tetapan di dalam kode. Nilai ambang
 * lingkungan berganti mengikuti peraturan yang berlaku, dan tiap izin
 * dapat menetapkan angka yang lebih ketat daripada ketentuan umumnya —
 * mengunci angkanya di dalam kode membuat modul ini kedaluwarsa bersama
 * satu versi regulasi, dan memaksa perubahan kode untuk hal yang
 * seharusnya cukup disunting petugas lingkungan.
 *
 * `acuan` menyimpan dasar hukum tiap baris supaya laporan dapat
 * menyebutkan angkanya berasal dari mana.
 */
#[ScopedBy(MilikPerusahaan::class)]
class LingkunganParameter extends Model
{
    use BerpemilikPerusahaan;

    public const MEDIA = ['air', 'udara', 'kebisingan', 'tanah'];

    protected $fillable = [
        'company_id', 'kode', 'nama', 'media', 'satuan',
        'batas_min', 'batas_maks', 'acuan', 'aktif',
    ];

    protected function casts(): array
    {
        return ['batas_min' => 'float', 'batas_maks' => 'float', 'aktif' => 'boolean'];
    }

    public function pantau(): HasMany { return $this->hasMany(LingkunganPantau::class); }

    /**
     * Apakah sebuah nilai melampaui baku mutu?
     *
     * Keduanya boleh kosong: pH punya batas atas dan bawah, debu hanya
     * batas atas, oksigen terlarut hanya batas bawah. Parameter tanpa
     * batas sama sekali tidak pernah dinyatakan melanggar — ia dipantau,
     * bukan ditaati.
     */
    public function melanggar(?float $nilai): bool
    {
        if ($nilai === null) return false;
        if ($this->batas_min !== null && $nilai < $this->batas_min) return true;
        if ($this->batas_maks !== null && $nilai > $this->batas_maks) return true;

        return false;
    }

    public function rentangLabel(): string
    {
        $s = $this->satuan ? ' '.$this->satuan : '';

        return match (true) {
            $this->batas_min !== null && $this->batas_maks !== null => "{$this->batas_min}–{$this->batas_maks}{$s}",
            $this->batas_maks !== null => "maks {$this->batas_maks}{$s}",
            $this->batas_min !== null  => "min {$this->batas_min}{$s}",
            default => 'tanpa ambang',
        };
    }

    public function toView(): array
    {
        return [
            'id'      => $this->id,
            'kode'    => $this->kode,
            'nama'    => $this->nama,
            'media'   => $this->media,
            'satuan'  => $this->satuan,
            'batas_min'  => $this->batas_min,
            'batas_maks' => $this->batas_maks,
            'rentang' => $this->rentangLabel(),
            'acuan'   => $this->acuan,
            'aktif'   => $this->aktif,
        ];
    }
}
