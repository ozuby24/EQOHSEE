<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Peledakan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bangunan atau tempat yang harus dilindungi dari getaran.
 *
 * Berdiri sebagai tabelnya sendiri karena rumah yang sama dilewati
 * banyak peledakan, dan ambang getarannya tidak berubah tiap kali.
 * Menyalinnya ke tiap rencana berarti satu perubahan izin harus
 * disunting di puluhan tempat — dan yang terlewat tidak menimbulkan
 * galat, hanya satu titik yang diam-diam dinilai dengan ambang lama.
 */
#[ScopedBy(MilikPerusahaan::class)]
class LedakTitik extends Model
{
    use BerpemilikPerusahaan;

    public const JENIS = ['permukiman', 'industri', 'peka', 'infrastruktur'];

    protected $fillable = [
        'company_id', 'kode', 'nama', 'jenis', 'lokasi',
        'ppv_ambang_mm_s', 'acuan_ambang', 'aktif', 'catatan',
    ];

    protected function casts(): array
    {
        return ['ppv_ambang_mm_s' => 'float', 'aktif' => 'boolean'];
    }

    public function ukur(): HasMany { return $this->hasMany(LedakUkur::class, 'ledak_titik_id'); }

    /**
     * Ambang yang berlaku bagi titik ini.
     *
     * Yang ditetapkan izin selalu menang. Bawaan menurut jenis bangunan
     * hanya dipakai selama izinnya belum diisikan — dan itu ditandai di
     * tampilan, sebab ambang bawaan bukan ambang yang mengikat.
     */
    public function ambang(): float
    {
        if ($this->ppv_ambang_mm_s !== null && $this->ppv_ambang_mm_s > 0) {
            return $this->ppv_ambang_mm_s;
        }

        return match ($this->jenis) {
            'peka'      => Peledakan::PPV_BANGUNAN_PEKA,
            'industri'  => Peledakan::PPV_INDUSTRI,
            default     => Peledakan::PPV_PERMUKIMAN,
        };
    }

    public function ambangDitetapkan(): bool
    {
        return $this->ppv_ambang_mm_s !== null && $this->ppv_ambang_mm_s > 0;
    }

    public function toView(): array
    {
        return [
            'id'     => $this->id,
            'kode'   => $this->kode,
            'nama'   => $this->nama,
            'jenis'  => $this->jenis,
            'lokasi' => $this->lokasi,
            'ambang' => $this->ambang(),
            'ambangDitetapkan' => $this->ambangDitetapkan(),
            'acuan'  => $this->acuan_ambang,
            'aktif'  => $this->aktif,
        ];
    }
}
