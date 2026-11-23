<?php

namespace App\Models\Perancah;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu inspeksi 16 butir oleh inspektur kompeten. Riwayat — tidak pernah diubah. */
#[ScopedBy(MilikPerusahaan::class)]
class Inspeksi extends Model
{
    use BerpemilikPerusahaan;

    public const PUTUSAN = ['aman', 'hati_hati', 'bahaya'];

    protected $table = 'perancah_inspeksi';

    protected $fillable = [
        'company_id', 'unit_id', 'user_id', 'jenis', 'inspektur', 'inspektur_sertifikat', 'hasil',
        'lulus', 'gagal_kritis', 'gagal_minor', 'putusan', 'syarat_khusus', 'tindakan_perbaikan',
        'diperiksa_at', 'berlaku_sampai',
    ];

    protected function casts(): array
    {
        return [
            'hasil' => 'array', 'lulus' => 'integer', 'gagal_kritis' => 'integer', 'gagal_minor' => 'integer',
            'diperiksa_at' => 'datetime', 'berlaku_sampai' => 'datetime',
        ];
    }

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class, 'unit_id'); }
    public function pencatat(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
}
