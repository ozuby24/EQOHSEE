<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\P2h;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/**
 * Unit yang wajib diperiksa sebelum bekerja, dengan keadaannya.
 *
 * `status` hanya dua: laik atau ditahan. Unit DITAHAN oleh P2H yang
 * menemukan butir kritis gagal, dan hanya DILEPAS oleh orang — pengawas
 * atau mekanik yang menulis alasannya — bukan oleh P2H berikutnya.
 * P2H shift berikut yang kebetulan lolos tidak membuktikan kerusakannya
 * sudah diperbaiki; ia hanya membuktikan operatornya menjawab berbeda.
 */
#[ScopedBy(MilikPerusahaan::class)]
class P2hUnit extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'p2h_unit';

    protected $fillable = [
        'company_id', 'ko_object_id', 'kode', 'nama', 'jenis', 'keterangan', 'hm',
        'status', 'ditahan_sejak', 'ditahan_karena', 'dilepas_oleh', 'dilepas_pada',
        'catatan_lepas', 'aktif',
    ];

    protected function casts(): array
    {
        return [
            'hm' => 'float', 'aktif' => 'boolean',
            'ditahan_sejak' => 'datetime', 'dilepas_pada' => 'datetime',
        ];
    }

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function objek(): BelongsTo    { return $this->belongsTo(KoObject::class, 'ko_object_id'); }
    public function periksa(): HasMany    { return $this->hasMany(P2hPeriksa::class, 'p2h_unit_id')->latest('id'); }
    public function terakhir(): HasOne    { return $this->hasOne(P2hPeriksa::class, 'p2h_unit_id')->latestOfMany(); }

    public function ditahan(): bool { return $this->status === P2h::DITAHAN; }

    public function namaJenis(): string { return P2h::JENIS[$this->jenis] ?? $this->jenis; }
}
