<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rekap bulanan (PICA) — potret angka pada akhir satu bulan.
 *
 * Disimpan sebagai potret, bukan dihitung ulang dari butir saat dibaca.
 * Butirnya berubah sepanjang tahun — dinilai ulang, ditambah, dinaikkan
 * statusnya — dan rekap Januari yang dihitung ulang pada bulan Desember
 * memulangkan angka Desember. Yang ditandatangani pada rapat Januari
 * adalah angka Januari.
 */
#[ScopedBy(MilikPerusahaan::class)]
class ComplianceRecap extends Model
{
    use BerpemilikPerusahaan;

    public const BULAN = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    protected $fillable = [
        'company_id', 'sumber', 'tahun', 'bulan',
        'comply', 'not_comply', 'na', 'belum', 'evaluasi', 'rencana',
    ];

    protected function casts(): array
    {
        return [
            'tahun' => 'integer', 'bulan' => 'integer',
            'comply' => 'integer', 'not_comply' => 'integer',
            'na' => 'integer', 'belum' => 'integer',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    public function namaBulan(): string { return self::BULAN[$this->bulan] ?? (string) $this->bulan; }

    /** Persentase pemenuhan bulan itu, dengan aturan yang sama di mana pun. */
    public function persen(): ?float
    {
        return \App\Support\Kepatuhan::persen($this->comply, $this->not_comply);
    }
}
