<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu pembacaan instrumen pada satu hari.
 *
 * Memakai alur tinjauan yang sama dengan modul lain, tetapi tujuannya di
 * sini berbeda dan perlu dinyatakan terang. Pada produksi, tinjauan
 * menjaga angka yang keluar dari perusahaan. Di sini, tinjauan adalah
 * pemeriksaan oleh orang yang berwenang atas pengamatan yang dapat
 * menghentikan pekerjaan — dan karena itu ia tidak boleh menjadi syarat
 * munculnya peringatan.
 *
 * Peringatan gerakan dihitung dari bacaan yang sudah disetujui, sebab
 * laju yang dihitung dari salah baca akan mengosongkan pit tanpa sebab.
 * Tetapi bacaan yang menunjukkan gejala — retakan baru, gugur batu,
 * rembesan — memicu peringatan sejak masih draf, tanpa menunggu siapa
 * pun. Menahan tanda bahaya sampai ada yang sempat menyetujuinya adalah
 * kekeliruan yang tidak dapat diperbaiki setelah lerengnya runtuh.
 */
#[ScopedBy(MilikPerusahaan::class)]
class GeoBacaan extends Model
{
    use BerpemilikPerusahaan;
    use Ditinjau;

    protected $fillable = [
        'company_id', 'user_id', 'geo_lereng_id', 'geo_instrumen_id', 'tanggal',
        'perpindahan_mm', 'retakan_mm', 'muka_air_m', 'curah_hujan_mm',
        'ada_gejala', 'gejala', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'        => 'date',
            'perpindahan_mm' => 'float',
            'retakan_mm'     => 'float',
            'muka_air_m'     => 'float',
            'curah_hujan_mm' => 'float',
            'ada_gejala'     => 'boolean',
            'diajukan_pada'  => 'datetime',
            'ditinjau_pada'  => 'datetime',
        ];
    }

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function lereng(): BelongsTo    { return $this->belongsTo(GeoLereng::class, 'geo_lereng_id'); }
    public function instrumen(): BelongsTo { return $this->belongsTo(GeoInstrumen::class, 'geo_instrumen_id'); }

    public function toView(): array
    {
        return [
            'id'             => $this->id,
            'tanggal'        => $this->tanggal?->toDateString(),
            'tanggalLabel'   => $this->tanggal?->format('d M Y'),
            'lereng'         => $this->lereng?->kode,
            'lerengId'       => $this->geo_lereng_id,
            'instrumen'      => $this->instrumen?->kode,
            'perpindahan_mm' => $this->perpindahan_mm,
            'retakan_mm'     => $this->retakan_mm,
            'muka_air_m'     => $this->muka_air_m,
            'curah_hujan_mm' => $this->curah_hujan_mm,
            'ada_gejala'     => $this->ada_gejala,
            'gejala'         => $this->gejala,
            'status'         => $this->status,
            'statusLabel'    => \App\Support\Alur::LABEL[$this->status] ?? $this->status,
            'catatan'        => $this->catatan,
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
