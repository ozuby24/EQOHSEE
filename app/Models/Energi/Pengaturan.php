<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Company;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengaturan energi & karbon satu perusahaan.
 *
 * Kategori pengguna energi menentukan ambang wajib manajemen energi PP
 * 33/2023 — dan kategorisasi tambang batubara belum dipastikan [A-01],
 * jadi ia pengaturan, bukan tetapan kode.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Pengaturan extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_pengaturan';

    /** Ambang TOE per tahun menurut PP 33/2023 Ps. 29, 31, 32, 34. */
    public const KATEGORI = [
        'industri'      => ['label' => 'Pengguna energi — industri',      'ambang' => 4000, 'dasar' => 'PP 33/2023 Ps. 32'],
        'transportasi'  => ['label' => 'Pengguna energi — transportasi',  'ambang' => 4000, 'dasar' => 'PP 33/2023 Ps. 31'],
        'penyedia'      => ['label' => 'Penyedia energi',                 'ambang' => 6000, 'dasar' => 'PP 33/2023 Ps. 29'],
        'bangunan'      => ['label' => 'Bangunan gedung',                 'ambang' => 500,  'dasar' => 'PP 33/2023 Ps. 34'],
    ];

    public const GWP = ['AR5' => 'IPCC AR5 (100 tahun)', 'AR6' => 'IPCC AR6 (100 tahun)'];

    protected $fillable = [
        'company_id', 'kategori_pengguna', 'ambang_toe', 'sistem_grid', 'gwp_set', 'emiten',
        'ambang_kelengkapan_persen', 'ambang_rekonsiliasi_persen', 'ambang_rag_persen',
        'tahun_dasar', 'ambang_rekalkulasi_persen', 'dasar_snapshot', 'target_tahun',
        'target_penurunan_persen', 'tanggal_proper', 'tanggal_laporan_tahunan',
    ];

    protected function casts(): array
    {
        return [
            'ambang_toe' => 'float', 'emiten' => 'boolean', 'ambang_kelengkapan_persen' => 'float',
            'ambang_rekonsiliasi_persen' => 'float', 'ambang_rag_persen' => 'float',
            'ambang_rekalkulasi_persen' => 'float', 'dasar_snapshot' => 'array',
            'target_penurunan_persen' => 'float', 'tanggal_proper' => 'date',
            'tanggal_laporan_tahunan' => 'date', 'tahun_dasar' => 'integer', 'target_tahun' => 'integer',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    /**
     * Pengaturan perusahaan, atau bawaan yang belum tersimpan.
     *
     * Dibaca tanpa scope karena perusahaannya disebut tegas: BOD dan
     * perintah konsol membaca pengaturan perusahaan yang datanya sedang
     * dihitung, bukan perusahaan penggunanya.
     */
    public static function untuk(?int $companyId): self
    {
        return self::withoutGlobalScopes()->where('company_id', $companyId)->first()
            ?? new self(['company_id' => $companyId, 'kategori_pengguna' => 'industri', 'gwp_set' => 'AR5', 'emiten' => false,
                         'ambang_kelengkapan_persen' => 95, 'ambang_rekonsiliasi_persen' => 2,
                         'ambang_rag_persen' => 5, 'ambang_rekalkulasi_persen' => 5]);
    }

    public function ambang(): float
    {
        return $this->ambang_toe ?: (float) (self::KATEGORI[$this->kategori_pengguna]['ambang'] ?? 4000);
    }
}
