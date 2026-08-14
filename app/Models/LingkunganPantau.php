<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu hasil uji pada satu titik penaatan.
 *
 * Pelanggaran baku mutu tidak disimpan sebagai kolom. Ambangnya dapat
 * berubah — peraturan diperbarui, izin diperketat — dan status yang
 * tersimpan akan tetap menyatakan "taat" pada baris lama sementara
 * ambangnya sudah bukan itu lagi. Karena itu pelanggarannya dihitung
 * ketika dibaca, terhadap ambang yang berlaku saat itu.
 */
#[ScopedBy(MilikPerusahaan::class)]
class LingkunganPantau extends Model
{
    use BerpemilikPerusahaan;
    use Ditinjau;

    protected $fillable = [
        'company_id', 'user_id', 'lingkungan_parameter_id', 'titik',
        'tanggal', 'nilai', 'laboratorium', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'       => 'date',
            'nilai'         => 'float',
            'diajukan_pada' => 'datetime',
            'ditinjau_pada' => 'datetime',
        ];
    }

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function parameter(): BelongsTo { return $this->belongsTo(LingkunganParameter::class, 'lingkungan_parameter_id'); }

    public function melanggar(): bool
    {
        return (bool) $this->parameter?->melanggar($this->nilai);
    }

    public function toView(): array
    {
        return [
            'id'           => $this->id,
            'tanggal'      => $this->tanggal?->toDateString(),
            'tanggalLabel' => $this->tanggal?->format('d M Y'),
            'titik'        => $this->titik,
            'parameter'    => $this->parameter?->nama,
            'kode'         => $this->parameter?->kode,
            'media'        => $this->parameter?->media,
            'satuan'       => $this->parameter?->satuan,
            'rentang'      => $this->parameter?->rentangLabel(),
            'acuan'        => $this->parameter?->acuan,
            'nilai'        => $this->nilai,
            'melanggar'    => $this->melanggar(),
            'laboratorium' => $this->laboratorium,
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
