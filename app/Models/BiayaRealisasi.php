<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Alur;
use App\Support\Biaya;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Realisasi biaya satu akun pada satu bulan dan satu pusat biaya.
 *
 * Inilah yang ditinjau, bukan anggarannya. Angka realisasi yang masuk
 * laporan pengendalian tanpa seorang pun memeriksanya adalah cara
 * termudah membuat biaya per ton terlihat baik: satu akun yang lupa
 * dicatat menurunkan seluruh indikator sekaligus, dan tidak ada galat
 * apa pun yang menandainya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class BiayaRealisasi extends Model
{
    use BerpemilikPerusahaan;
    use Ditinjau;

    /** `status` sengaja tidak ada di sini — ia hanya berpindah lewat Ditinjau. */
    protected $fillable = [
        'company_id', 'user_id', 'biaya_akun_id', 'tahun', 'bulan',
        'pusat_biaya', 'nilai_rp', 'kuantitas', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tahun'         => 'integer',
            'bulan'         => 'integer',
            'nilai_rp'      => 'float',
            'kuantitas'     => 'float',
            'diajukan_pada' => 'datetime',
            'ditinjau_pada' => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function akun(): BelongsTo    { return $this->belongsTo(BiayaAkun::class, 'biaya_akun_id'); }

    /** Harga nyata per satuan — diturunkan, tidak disimpan. */
    public function hargaNyata(): ?float
    {
        return Biaya::perSatuan($this->nilai_rp, $this->kuantitas);
    }

    public function toView(): array
    {
        return [
            'id'        => $this->id,
            'akunId'    => $this->biaya_akun_id,
            'akun'      => $this->akun?->kode,
            'akunNama'  => $this->akun?->nama,
            'satuan'    => $this->akun?->satuan,
            'tahun'     => $this->tahun,
            'bulan'     => $this->bulan,
            'pusat'     => $this->pusat_biaya,
            'nilai'     => $this->nilai_rp,
            'kuantitas' => $this->kuantitas,
            'harga'     => $this->hargaNyata(),
            'catatan'   => $this->catatan,

            'status'      => $this->status,
            'statusLabel' => Alur::LABEL[$this->status] ?? $this->status,
            'alur' => [
                'dapatDiubah'   => $this->dapatDiubah(),
                'dapatDiajukan' => $this->dapatDiubah(),
                'dapatDitinjau' => $this->dapatDitinjauOleh(auth()->user()),
                'pengaju'       => $this->pengaju?->name,
                'peninjau'      => $this->peninjau?->name,
                'alasanTolak'   => $this->alasan_tolak,
            ],
        ];
    }
}
