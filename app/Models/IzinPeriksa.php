<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jawaban satu baris daftar periksa pada satu izin.
 *
 * Teks syaratnya disalin, tidak dirujuk. Daftar periksa berubah seiring
 * waktu, dan izin yang sudah diterbitkan harus tetap terbaca dengan
 * syarat yang berlaku SAAT itu — bukan dengan syarat yang berlaku saat
 * berkasnya dibuka kembali setahun kemudian.
 */
#[ScopedBy(MilikPerusahaan::class)]
class IzinPeriksa extends Model
{
    use BerpemilikPerusahaan;

    protected $fillable = [
        'company_id', 'izin_kerja_id', 'izin_syarat_id', 'teks', 'wajib', 'terpenuhi', 'keterangan',
    ];

    protected function casts(): array
    {
        return ['wajib' => 'boolean', 'terpenuhi' => 'boolean'];
    }

    public function izin(): BelongsTo { return $this->belongsTo(IzinKerja::class, 'izin_kerja_id'); }

    public function toView(): array
    {
        return [
            'id' => $this->id, 'teks' => $this->teks, 'wajib' => $this->wajib,
            'terpenuhi' => $this->terpenuhi, 'keterangan' => $this->keterangan,
        ];
    }
}
