<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\User;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Manajer Energi atau Auditor Energi bersertifikat kompetensi. */
#[ScopedBy(MilikPerusahaan::class)]
class Personel extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_personel';

    public const PERAN = ['manajer_energi' => 'Manajer Energi', 'auditor_energi' => 'Auditor Energi'];

    protected $fillable = ['company_id', 'user_id', 'nama', 'peran', 'internal', 'lembaga', 'nomor_sertifikat',
                           'lembaga_sertifikasi', 'berlaku_sampai', 'nomor_sk', 'aktif'];

    protected function casts(): array
    {
        return ['internal' => 'boolean', 'aktif' => 'boolean', 'berlaku_sampai' => 'date'];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Bersertifikat dan sertifikatnya masih berlaku pada tanggal itu. */
    public function sah(?\DateTimeInterface $pada = null): bool
    {
        $pada ??= now();

        return $this->aktif && filled($this->nomor_sertifikat)
            && $this->berlaku_sampai !== null && $this->berlaku_sampai->greaterThanOrEqualTo($pada->format('Y-m-d'));
    }
}
