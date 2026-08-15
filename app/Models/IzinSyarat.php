<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris daftar periksa pada satu jenis izin.
 *
 * Data, bukan daftar di dalam kode: tiap perusahaan memakai daftarnya
 * sendiri, dan daftar itu bertambah setiap kali ada kejadian yang
 * menambah satu baris ke dalamnya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class IzinSyarat extends Model
{
    use BerpemilikPerusahaan;

    protected $fillable = ['company_id', 'jenis', 'urutan', 'teks', 'wajib', 'aktif'];

    protected function casts(): array
    {
        return ['wajib' => 'boolean', 'aktif' => 'boolean', 'urutan' => 'integer'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    public function toView(): array
    {
        return [
            'id' => $this->id, 'jenis' => $this->jenis, 'urutan' => $this->urutan,
            'teks' => $this->teks, 'wajib' => $this->wajib, 'aktif' => $this->aktif,
        ];
    }
}
