<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Reklamasi;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu laporan kemajuan reklamasi pada sebuah petak.
 *
 * Yang dicatat adalah tahapan yang dicapai pada tanggal itu beserta
 * luasnya. Tahapan petaknya sendiri baru berpindah setelah laporan ini
 * disetujui — kemajuan yang belum ditinjau tidak boleh menggeser neraca
 * lahan yang masuk ke laporan triwulan kepada inspektur tambang.
 */
#[ScopedBy(MilikPerusahaan::class)]
class ReklamasiKemajuan extends Model
{
    use BerpemilikPerusahaan;
    use Ditinjau;

    protected $fillable = [
        'company_id', 'user_id', 'lingkungan_area_id', 'tanggal', 'tahap',
        'luas_ha', 'pohon_ditanam', 'tingkat_tumbuh_persen', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'               => 'date',
            'luas_ha'               => 'float',
            'tingkat_tumbuh_persen' => 'float',
            'diajukan_pada'         => 'datetime',
            'ditinjau_pada'         => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function area(): BelongsTo    { return $this->belongsTo(LingkunganArea::class, 'lingkungan_area_id'); }

    public function toView(): array
    {
        return [
            'id'           => $this->id,
            'tanggal'      => $this->tanggal?->toDateString(),
            'tanggalLabel' => $this->tanggal?->format('d M Y'),
            'area'         => $this->area?->kode,
            'areaId'       => $this->lingkungan_area_id,
            'tahap'        => $this->tahap,
            'tahapLabel'   => Reklamasi::TAHAP[$this->tahap] ?? $this->tahap,
            'luas_ha'      => $this->luas_ha,
            'pohon_ditanam'=> $this->pohon_ditanam,
            'tingkat_tumbuh_persen' => $this->tingkat_tumbuh_persen,
            'status'       => $this->status,
            'statusLabel'  => \App\Support\Alur::LABEL[$this->status] ?? $this->status,
            'catatan'      => $this->catatan,
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
