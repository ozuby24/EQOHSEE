<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Izin;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Satu pengukuran gas pada satu izin.
 *
 * Tidak memakai alur tinjauan — pembacaan alat, bukan pendapat. Yang
 * menentukan bukan siapa yang menyetujuinya melainkan kapan ia diambil:
 * kadar gas berubah dalam hitungan menit, dan angka yang benar tiga jam
 * lalu tidak menyatakan apa pun tentang keadaan sekarang.
 */
#[ScopedBy(MilikPerusahaan::class)]
class IzinGas extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'izin_gas';

    protected $fillable = [
        'company_id', 'izin_kerja_id', 'waktu_uji', 'o2', 'lel', 'co', 'h2s',
        'alat', 'petugas', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'waktu_uji' => 'datetime',
            'o2' => 'float', 'lel' => 'float', 'co' => 'float', 'h2s' => 'float',
        ];
    }

    public function izin(): BelongsTo { return $this->belongsTo(IzinKerja::class, 'izin_kerja_id'); }

    /** @return array<string,?float> */
    public function bacaan(): array
    {
        return ['o2' => $this->o2, 'lel' => $this->lel, 'co' => $this->co, 'h2s' => $this->h2s];
    }

    public function periksa(array $ambang): array
    {
        return Izin::periksaGas($this->bacaan(), $ambang);
    }

    public function segar(?Carbon $sekarang = null, ?int $batasMenit = null): bool
    {
        return Izin::ujiSegar($this->waktu_uji, $sekarang, $batasMenit);
    }

    public function toView(array $ambang = [], ?int $batasMenit = null): array
    {
        $hasil = $ambang === [] ? null : $this->periksa($ambang);

        return [
            'id'      => $this->id,
            'waktu'   => $this->waktu_uji?->format('d M Y H:i'),
            'usia'    => Izin::usiaUji($this->waktu_uji),
            'segar'   => $this->segar(null, $batasMenit),
            'o2' => $this->o2, 'lel' => $this->lel, 'co' => $this->co, 'h2s' => $this->h2s,
            'alat'    => $this->alat,
            'petugas' => $this->petugas,
            'lulus'   => $hasil['lulus'] ?? null,
            'lengkap' => $hasil['lengkap'] ?? null,
            'rinci'   => $hasil['rinci'] ?? [],
        ];
    }
}
