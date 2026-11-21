<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\{Company, User};
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/** Satu bulan kalender satu perusahaan — satuan kunci data aktivitas. */
#[ScopedBy(MilikPerusahaan::class)]
class Periode extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_periode';

    public const STATUS = ['terbuka', 'verifikasi', 'terkunci'];

    protected $fillable = ['company_id', 'tahun', 'bulan', 'status', 'dikunci_oleh', 'dikunci_pada',
                           'kelengkapan_persen', 'catatan'];

    protected function casts(): array
    {
        return ['tahun' => 'integer', 'bulan' => 'integer', 'dikunci_pada' => 'datetime',
                'kelengkapan_persen' => 'float'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function pengunci(): BelongsTo { return $this->belongsTo(User::class, 'dikunci_oleh'); }
    public function koreksi(): HasMany { return $this->hasMany(Koreksi::class, 'periode_id'); }

    public function terkunci(): bool { return $this->status === 'terkunci'; }

    public function mulai(): Carbon  { return Carbon::create($this->tahun, $this->bulan, 1)->startOfDay(); }
    public function sampai(): Carbon { return $this->mulai()->endOfMonth()->startOfDay(); }

    public function label(): string
    {
        return $this->mulai()->translatedFormat('F Y');
    }

    /**
     * Apakah tanggal ini jatuh pada periode terkunci perusahaan itu?
     *
     * Dibaca tanpa scope: perusahaannya disebut tegas, dan yang ditanyakan
     * adalah keadaan periode milik baris yang hendak diubah — bukan milik
     * penggunanya.
     */
    public static function terkunciPada(?int $companyId, \DateTimeInterface|string $tanggal): bool
    {
        $t = Carbon::parse($tanggal);

        return self::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('tahun', $t->year)->where('bulan', $t->month)
            ->where('status', 'terkunci')->exists();
    }

    public static function untuk(?int $companyId, int $tahun, int $bulan): self
    {
        return self::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $companyId, 'tahun' => $tahun, 'bulan' => $bulan],
            ['status' => 'terbuka'],
        );
    }
}
