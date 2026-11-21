<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\{Company, User};
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Energi\RegistriFaktor;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu versi faktor konversi/emisi — berlaku pada rentang tanggal tertentu.
 *
 * Faktor tidak pernah diubah di tempat setelah aktif: versi baru dibuat
 * dengan berlaku_mulai yang baru, dan versi lama ditutup berlaku_sampai-nya.
 * Dengan begitu tCO₂e tahun lalu tetap dihitung dengan faktor tahun lalu.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Faktor extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_faktor';

    public const STATUS = ['draf', 'aktif', 'ditarik'];

    protected $fillable = [
        'company_id', 'kode_energi', 'parameter', 'kategori_ipcc', 'wilayah', 'pemasok',
        'nilai', 'satuan', 'berlaku_mulai', 'berlaku_sampai', 'tier', 'sumber_dokumen',
        'sumber_rujukan', 'sumber_url', 'ketidakpastian_persen', 'status', 'perlu_cocok',
        'diajukan_oleh', 'disetujui_oleh', 'disetujui_pada', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'nilai' => 'float', 'berlaku_mulai' => 'date', 'berlaku_sampai' => 'date',
            'tier' => 'integer', 'ketidakpastian_persen' => 'float', 'perlu_cocok' => 'boolean',
            'disetujui_pada' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Cache registri per permintaan harus tahu bahwa isinya berubah.
        static::saved(fn () => RegistriFaktor::lupakan());
        static::deleted(fn () => RegistriFaktor::lupakan());
    }

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function pengaju(): BelongsTo   { return $this->belongsTo(User::class, 'diajukan_oleh'); }
    public function penyetuju(): BelongsTo { return $this->belongsTo(User::class, 'disetujui_oleh'); }

    /** Ringkasan yang dibekukan ke snapshot laporan. */
    public function ringkas(): array
    {
        return [
            'id' => $this->id, 'kode_energi' => $this->kode_energi, 'parameter' => $this->parameter,
            'kategori_ipcc' => $this->kategori_ipcc, 'wilayah' => $this->wilayah,
            'nilai' => $this->nilai, 'satuan' => $this->satuan,
            'berlaku_mulai' => $this->berlaku_mulai?->toDateString(),
            'berlaku_sampai' => $this->berlaku_sampai?->toDateString(),
            'tier' => $this->tier, 'sumber_dokumen' => $this->sumber_dokumen,
            'sumber_rujukan' => $this->sumber_rujukan, 'perlu_cocok' => $this->perlu_cocok,
        ];
    }
}
