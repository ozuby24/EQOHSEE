<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Pekerja;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Waktu;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Upah yang berlaku sejak sebuah tanggal.
 *
 * BERTANGGAL-BERLAKU, bukan satu kolom pada baris pekerja. Upah naik,
 * dan lembur bulan lalu harus tetap terhitung dengan upah yang berlaku
 * saat itu. Disimpan sebagai satu kolom yang ditimpa, kenaikan upah
 * bulan ini diam-diam menaikkan nilai seluruh lembur tahun lalu — dan
 * tidak ada satu galat pun yang menandainya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Upah extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'hr_upah';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'berlaku_mulai'         => 'date',
            'pokok'                 => 'float',
            'tunjangan_tetap'       => 'float',
            'tunjangan_tidak_tetap' => 'float',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function pekerja(): BelongsTo { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }

    public function seluruhnya(): float
    {
        return $this->pokok + $this->tunjangan_tetap + $this->tunjangan_tidak_tetap;
    }

    /**
     * Upah yang berlaku bagi seseorang pada sebuah tanggal.
     *
     * DIBANDINGKAN SEBAGAI UNTAIAN TANGGAL. `berlaku_mulai` bertipe
     * DATE tetapi menyimpan "2026-09-01 00:00:00"; dibandingkan sebagai
     * saat terhadap Carbon berzona WITA, upah yang berlaku mulai hari
     * ini belum terbaca sampai pukul delapan pagi.
     */
    public static function pada(Pekerja $p, Carbon $tanggal): ?self
    {
        return static::withoutGlobalScopes()
            ->where('pekerja_id', $p->id)
            ->where('berlaku_mulai', '<=', $tanggal->toDateString().' 23:59:59')
            ->orderByDesc('berlaku_mulai')
            ->first();
    }

    /** Upah yang berlaku hari ini. */
    public static function kini(Pekerja $p): ?self
    {
        return self::pada($p, Waktu::kini());
    }
}
