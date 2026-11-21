<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

use App\Support\Energi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Peluang penghematan energi yang sedang ditindaklanjuti. */
#[ScopedBy(MilikPerusahaan::class)]
class EnergyOpportunity extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energy_opportunities';

    protected $fillable = ['company_id','judul','area','status','uraian','hemat_liter','hemat_kwh',
                           'penanggung_jawab','target_selesai','user_id',
                           'capex_rp','metode_mv','hemat_terverifikasi_liter','hemat_terverifikasi_kwh',
                           'tanggal_mulai_hemat','mv_catatan'];

    protected function casts(): array
    {
        return ['hemat_liter' => 'float', 'hemat_kwh' => 'float', 'target_selesai' => 'date',
                'capex_rp' => 'float', 'hemat_terverifikasi_liter' => 'float', 'hemat_terverifikasi_kwh' => 'float',
                'tanggal_mulai_hemat' => 'date'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    /* Penghematan per bulan, diturunkan dari satuan asalnya. */
    public function gj(): float    { return Energi::literKeGj($this->hemat_liter) + Energi::kwhKeGj($this->hemat_kwh); }
    public function tco2e(): float { return Energi::literKeCo2($this->hemat_liter) + Energi::kwhKeCo2($this->hemat_kwh); }
    public function rupiah(): float{ return Energi::literKeRp($this->hemat_liter) + Energi::kwhKeRp($this->hemat_kwh); }

    /* Penghematan TERVERIFIKASI (M&V ISO 50015 / IPMVP) per bulan. Hanya
       angka ini yang boleh disebut "terwujud" pada laporan regulator dan
       dasbor direksi; perkiraan di atas tetap angka rencana. */
    public function gjTerverifikasi(): float
    {
        return Energi::literKeGj((float) $this->hemat_terverifikasi_liter) + Energi::kwhKeGj((float) $this->hemat_terverifikasi_kwh);
    }

    public function tco2eTerverifikasi(): float
    {
        return Energi::literKeCo2((float) $this->hemat_terverifikasi_liter) + Energi::kwhKeCo2((float) $this->hemat_terverifikasi_kwh);
    }

    public function rupiahTerverifikasi(): float
    {
        return Energi::literKeRp((float) $this->hemat_terverifikasi_liter) + Energi::kwhKeRp((float) $this->hemat_terverifikasi_kwh);
    }

    public function terverifikasi(): bool
    {
        return $this->status === 'selesai' && filled($this->metode_mv)
            && ((float) $this->hemat_terverifikasi_liter > 0 || (float) $this->hemat_terverifikasi_kwh > 0);
    }

    /** Masa balik modal dalam tahun: CAPEX ÷ penghematan rupiah setahun. */
    public function payback(): ?float
    {
        $tahunan = $this->rupiah() * 12;

        return ($this->capex_rp && $tahunan > 0) ? $this->capex_rp / $tahunan : null;
    }

    /** Sudah terwujud, jadi boleh dihitung sebagai penghematan nyata. */
    public function terwujud(): bool { return in_array($this->status, ['berjalan','selesai'], true); }
}
