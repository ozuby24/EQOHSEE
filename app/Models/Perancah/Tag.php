<?php

namespace App\Models\Perancah;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scafftag yang pernah diterbitkan. Tag baru menonaktifkan yang lama;
 * tidak ada yang dihapus atau diubah warnanya sesudah terbit.
 *
 * `token` acak dipakai alamat verifikasi QR tanpa masuk, terpisah dari
 * `kode` yang tercetak: kode berurutan mudah ditebak, dan alamat yang
 * dapat ditebak membuka data perusahaan bagi siapa pun.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Tag extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'perancah_tag';

    protected $fillable = [
        'company_id', 'unit_id', 'inspeksi_id', 'kode', 'token', 'warna', 'label', 'swl_kg_m2',
        'syarat_khusus', 'alasan_tolak', 'terbit_oleh', 'terbit_at', 'kedaluwarsa_at', 'aktif',
    ];

    protected function casts(): array
    {
        return ['aktif' => 'boolean', 'terbit_at' => 'datetime', 'kedaluwarsa_at' => 'datetime', 'swl_kg_m2' => 'integer'];
    }

    public function unit(): BelongsTo { return $this->belongsTo(Unit::class, 'unit_id'); }
    public function inspeksi(): BelongsTo { return $this->belongsTo(Inspeksi::class, 'inspeksi_id'); }

    public function getRouteKeyName(): string { return 'kode'; }

    public function kedaluwarsa(): bool
    {
        return $this->warna !== 'merah' && $this->kedaluwarsa_at && $this->kedaluwarsa_at->isPast();
    }
}
