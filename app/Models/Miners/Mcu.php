<?php

namespace App\Models\Miners;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Concerns\PunyaAlur;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Satu surat pengantar MCU ke klinik, memuat banyak nama.
 *
 * Bentuk aslinya di lapangan, dan karena itu bentuknya di sini:
 * perusahaan mengirim SATU surat berisi daftar orang, bukan satu surat
 * per orang. Dimodelkan per orang, nomor suratnya harus dikarang ulang
 * untuk tiap baris — dan yang dipegang klinik tinggal satu kertas yang
 * tidak cocok dengan satu pun di antaranya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Mcu extends Model
{
    use BerpemilikPerusahaan;
    use PunyaAlur;

    protected static string $jenisDokumen = 'mcu';

    public const STATUS = [
        'draf'      => 'Draf',
        'diajukan'  => 'Diajukan',
        'diperiksa' => 'Sedang Diperiksa',
        'selesai'   => 'Selesai',
        'ditolak'   => 'Ditolak',
    ];

    protected $table = 'mnr_mcu';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draf'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }

    public function orang(): HasMany
    {
        return $this->hasMany(McuOrang::class, 'mcu_id')->orderBy('nama');
    }

    public function bolehDiajukan(): bool
    {
        return $this->status === 'draf' && $this->orang()->exists();
    }
}
