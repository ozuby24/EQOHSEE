<?php

namespace App\Models\Frop;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Pekerja;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use App\Support\Frop\Penilaian;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Satu sesi observasi operator loader.
 *
 * Yang disimpan hanya yang diukur dan dicatat di lapangan. Aktual CT,
 * status, kesimpulan, dan rekomendasi coaching dihitung Penilaian dari
 * {@see nilai()} setiap kali dibaca.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Observasi extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'frop_observasi';

    public const STATUS_CA = ['Open', 'In Progress', 'Closed'];

    /* Pilihan isian — Panduan Pengisian dan isi berkas asalnya. */
    public const KONDISI_MESIN = ['High Perform', 'Normal', 'Low Perform'];
    public const MODE_KERJA = ['E (Economy)', 'P (Power)', 'Power+'];
    public const OPERATING_CONDITION = ['Good', 'Average', 'Poor'];
    public const METODE_LOADING = ['Side Loading', 'Front Loading'];
    public const MTO = ['MTO', 'Non-MTO'];
    public const KONDISI_PERMUKAAN = ['Normal', 'Undulating', 'Irregular'];
    public const CUACA = ['Cerah', 'Berawan', 'Hujan Ringan', 'Hujan Lebat'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal'        => 'date',
            'deadline_ca'    => 'date',
            'selesai_ca'     => 'date',
            'shift'          => 'integer',
            'boulder'        => 'boolean',
            'bucket_heap'    => 'boolean',
            'tinggi_jenjang' => 'float',
            'lebar_front'    => 'float',
            'spotting'       => 'float',
            'digging'        => 'float',
            'swl'            => 'float',
            'dump'           => 'float',
            'swe'            => 'float',
            'plan_ct'        => 'float',
            'loading_detik'  => 'integer',
            'target_pty'     => 'integer',
            'aktual_pty'     => 'integer',
        ];
    }

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function pekerja(): BelongsTo  { return $this->belongsTo(Pekerja::class); }
    public function coaching(): HasMany   { return $this->hasMany(Coaching::class, 'observasi_id'); }

    /** Bentuk yang dibaca Penilaian. */
    public function nilai(): array
    {
        return [
            'id'            => $this->id,
            'tanggal'       => $this->tanggal?->toDateString(),
            'unit'          => $this->unit,
            'operator'      => $this->operator,
            'level'         => $this->level,
            'plan_ct'       => $this->plan_ct,
            'spotting'      => $this->spotting,
            'digging'       => $this->digging,
            'swl'           => $this->swl,
            'dump'          => $this->dump,
            'swe'           => $this->swe,
            'loading_detik' => $this->loading_detik,
            'n_passing'     => $this->n_passing,
            'bucket_heap'   => $this->bucket_heap,
            'target_pty'    => $this->target_pty,
            'aktual_pty'    => $this->aktual_pty,
            'temuan'        => $this->temuan,
            'status_ca'     => $this->status_ca,
        ];
    }

    /** Loading time sebagai m:ss, seperti di lembar kerjanya. */
    public function loadingTeks(): ?string
    {
        return self::detikKeTeks($this->loading_detik);
    }

    public static function detikKeTeks(?int $d): ?string
    {
        return $d === null ? null : intdiv($d, 60).':'.str_pad((string) ($d % 60), 2, '0', STR_PAD_LEFT);
    }

    /**
     * "1:29", "01:29", "89" → detik.
     *
     * Isian tanpa titik dua dibaca sebagai detik, bukan menit: loading
     * satu hauler berkisar satu sampai tiga menit, dan "89" menit bukan
     * angka yang mungkin.
     */
    public static function teksKeDetik(?string $t): ?int
    {
        $t = trim((string) $t);
        if ($t === '') return null;

        if (preg_match('/^(\d{1,2}):([0-5]\d)$/', $t, $m)) return (int) $m[1] * 60 + (int) $m[2];
        if (preg_match('/^\d{1,4}$/', $t)) return (int) $t;

        return null;
    }

    /** Plan CT yang berlaku bagi level ini. */
    public static function planUntuk(string $level): float
    {
        return Penilaian::planCt($level) ?? 0.0;
    }
}
