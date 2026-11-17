<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Subjek yang dinilai pemenuhannya.
 *
 * Satu peraturan perundangan, satu standar ISO, atau satu dokumen
 * terkendali — yang membedakan hanya `sumber`. Penilaiannya, cara
 * menghitung persentasenya, dasbornya, dan ekspornya sama untuk
 * ketiganya, dan karena itu tabelnya juga satu.
 */
#[ScopedBy(MilikPerusahaan::class)]
class ComplianceSubject extends Model
{
    use BerpemilikPerusahaan;

    public const SUMBER = ['Peraturan', 'ISO', 'Dokumen'];
    public const STATUS = ['Draf', 'Tetap'];

    protected $fillable = [
        'company_id', 'user_id', 'sumber', 'kode', 'jenis', 'nomor', 'judul',
        'tanggal_terbit', 'instansi', 'aspek', 'ruang_lingkup', 'rangkuman',
        'tahun', 'status', 'dari_ai', 'document_id', 'iso_kode', 'berkas',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_terbit' => 'date',
            'tahun'          => 'integer',
            'dari_ai'        => 'boolean',
            'berkas'         => 'array',
        ];
    }

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function document(): BelongsTo { return $this->belongsTo(Document::class); }
    public function points(): HasMany     { return $this->hasMany(CompliancePoint::class, 'subject_id')->orderBy('order_index'); }

    /**
     * Rekap butirnya.
     *
     * Dihitung dari koleksi yang SUDAH dimuat bila ada, supaya daftar
     * berisi dua ratus peraturan tidak menerbitkan dua ratus kueri
     * hitung. Yang memanggilnya dari daftar cukup `with('points')`.
     */
    public function rekap(): array
    {
        $p = $this->relationLoaded('points') ? $this->points : $this->points()->get();

        return \App\Support\Kepatuhan::rekap($p->pluck('status')->all());
    }

    /** Draf hasil rangkuman mesin belum ikut menentukan angka apa pun. */
    public function scopeTetap($q) { return $q->where('status', 'Tetap'); }
}
