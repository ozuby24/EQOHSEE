<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan harian sebuah kolam.
 *
 * Memakai alur tinjauan yang sama dengan data produksi, dan untuk alasan
 * yang sama: angka debit dan kualitas air masuk ke laporan yang keluar
 * dari perusahaan. Berbeda dari waktu henti pemeliharaan, mengeluarkan
 * catatan yang belum ditinjau di sini tidak menyanjung apa pun — ia
 * hanya membuat cakupannya terlihat kurang, sebagaimana mestinya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class WaterLog extends Model
{
    use BerpemilikPerusahaan;
    use Ditinjau;

    /** `status` dikelola Ditinjau, bukan diisi dari formulir. */
    protected $fillable = [
        'company_id', 'user_id', 'water_sump_id', 'tanggal',
        'curah_hujan_mm', 'level_m', 'volume_m3',
        'debit_masuk_m3', 'debit_keluar_m3', 'jam_pompa', 'energi_kwh',
        'ph', 'tss_mgl', 'fe_mgl', 'mn_mgl', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal'         => 'date',
            'curah_hujan_mm'  => 'float',
            'level_m'         => 'float',
            'volume_m3'       => 'float',
            'debit_masuk_m3'  => 'float',
            'debit_keluar_m3' => 'float',
            'jam_pompa'       => 'float',
            'energi_kwh'      => 'float',
            'ph'              => 'float',
            'tss_mgl'         => 'float',
            'fe_mgl'          => 'float',
            'mn_mgl'          => 'float',
            'diajukan_pada'   => 'datetime',
            'ditinjau_pada'   => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function sump(): BelongsTo    { return $this->belongsTo(WaterSump::class, 'water_sump_id'); }

    /**
     * Debit keluar per jam pompa berjalan.
     *
     * Membaginya dengan lamanya hari, bukan dengan jam pompa, membuat
     * pompa yang jarang dinyalakan terlihat lemah padahal ia bekerja
     * baik selama menyala.
     */
    public function debitPerJam(): ?float
    {
        return $this->jam_pompa > 0 ? round($this->debit_keluar_m3 / $this->jam_pompa, 2) : null;
    }

    /** Kilowatt-jam per meter kubik yang dipompa. */
    public function energiPerM3(): ?float
    {
        return $this->debit_keluar_m3 > 0 ? round($this->energi_kwh / $this->debit_keluar_m3, 4) : null;
    }

    public function adaSampelAir(): bool
    {
        return $this->ph !== null || $this->tss_mgl !== null
            || $this->fe_mgl !== null || $this->mn_mgl !== null;
    }

    public function toView(): array
    {
        return [
            'id'              => $this->id,
            'tanggal'         => $this->tanggal?->toDateString(),
            'tanggalLabel'    => $this->tanggal?->format('d M Y'),
            'sump'            => $this->sump?->kode,
            'curah_hujan_mm'  => $this->curah_hujan_mm,
            'level_m'         => $this->level_m,
            'volume_m3'       => $this->volume_m3,
            'debit_masuk_m3'  => $this->debit_masuk_m3,
            'debit_keluar_m3' => $this->debit_keluar_m3,
            'jam_pompa'       => $this->jam_pompa,
            'debitPerJam'     => $this->debitPerJam(),
            'energiPerM3'     => $this->energiPerM3(),
            'ph'              => $this->ph,
            'tss_mgl'         => $this->tss_mgl,
            'fe_mgl'          => $this->fe_mgl,
            'mn_mgl'          => $this->mn_mgl,
            'adaSampel'       => $this->adaSampelAir(),
            'status'          => $this->status,
            'statusLabel'     => \App\Support\Alur::LABEL[$this->status] ?? $this->status,
            'catatan'         => $this->catatan,
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
