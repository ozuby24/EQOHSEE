<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Company;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Lokasi operasi — pit, port, hauling road, camp — beserta sistem gridnya. */
#[ScopedBy(MilikPerusahaan::class)]
class Site extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_sites';

    public const JENIS = [
        'pit' => 'Pit', 'port' => 'Pelabuhan', 'hauling' => 'Hauling road', 'camp' => 'Camp',
        'plant' => 'Processing plant', 'kantor' => 'Kantor', 'lain' => 'Lainnya',
    ];

    /** Sistem ketenagalistrikan — kunci faktor grid Scope 2 (Kepmen ESDM 163.K/2021). */
    public const SISTEM_GRID = [
        'JAMALI' => 'Jawa-Madura-Bali', 'SUMATERA' => 'Sumatera', 'KALBAR' => 'Kalimantan Barat',
        'KALSELTENGTIMUTARA' => 'Kalimantan Selatan-Tengah-Timur-Utara', 'SULBAGUT' => 'Sulawesi bagian utara',
        'SULBAGSEL' => 'Sulawesi bagian selatan', 'ISOLATED' => 'Sistem terisolasi / lainnya',
    ];

    public const ZONA = ['Asia/Jakarta' => 'WIB', 'Asia/Makassar' => 'WITA', 'Asia/Jayapura' => 'WIT'];

    protected $fillable = ['company_id', 'kode', 'nama', 'jenis', 'provinsi', 'sistem_grid', 'zona_waktu', 'aktif'];

    protected function casts(): array { return ['aktif' => 'boolean']; }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
}
