<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Perintah kerja pemeliharaan.
 *
 * Alatnya diambil dari registri Keselamatan Operasi, bukan didaftarkan
 * ulang: registri kedua berarti dua daftar alat yang sama-sama mengaku
 * benar, dan yang satu pasti tertinggal.
 */
#[ScopedBy(MilikPerusahaan::class)]
class WorkOrder extends Model
{
    use BerpemilikPerusahaan;

    public const JENIS = ['preventif', 'korektif', 'prediktif', 'darurat'];
    public const STATUS = ['dibuka', 'dikerjakan', 'selesai', 'batal'];
    public const PRIORITAS = ['rendah', 'sedang', 'tinggi', 'kritis'];

    /** Status yang berarti alatnya masih belum kembali bekerja. */
    public const TERBUKA = ['dibuka', 'dikerjakan'];

    /**
     * Hanya gangguan tak terencana yang dihitung sebagai kegagalan.
     *
     * Perawatan berkala juga menghentikan alat, tetapi menghitungnya
     * sebagai kegagalan membuat MTBF memburuk justru ketika perawatannya
     * dijalankan rajin — persis kebalikan dari yang hendak didorong.
     */
    public const KEGAGALAN = ['korektif', 'darurat'];

    protected $fillable = [
        'company_id', 'user_id', 'ko_object_id', 'nomor', 'jenis', 'prioritas', 'status',
        'gejala', 'penyebab', 'tindakan',
        'dilaporkan_pada', 'mulai_pada', 'selesai_pada',
        'hm_saat_rusak', 'biaya',
        // Penutup dan penverifikasi diisi oleh perpindahan status, bukan
        // dari payload; keduanya tetap di sini agar controller dapat
        // menuliskannya lewat update() yang sama.
        'ditutup_oleh', 'diverifikasi_oleh', 'diverifikasi_pada',
    ];

    protected function casts(): array
    {
        return [
            'dilaporkan_pada' => 'datetime',
            'mulai_pada'      => 'datetime',
            'selesai_pada'    => 'datetime',
            'hm_saat_rusak'     => 'float',
            'biaya'             => 'float',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function objek(): BelongsTo   { return $this->belongsTo(KoObject::class, 'ko_object_id'); }
    public function penutup(): BelongsTo { return $this->belongsTo(User::class, 'ditutup_oleh'); }
    public function pemverifikasi(): BelongsTo { return $this->belongsTo(User::class, 'diverifikasi_oleh'); }
    public function parts(): HasMany     { return $this->hasMany(WorkOrderPart::class); }

    /* ---------- waktu ---------- */

    public function terbuka(): bool
    {
        return in_array($this->status, self::TERBUKA, true);
    }

    public function kegagalan(): bool
    {
        return in_array($this->jenis, self::KEGAGALAN, true);
    }

    /**
     * Jam alat berhenti berproduksi: sejak dilaporkan sampai selesai.
     *
     * Yang belum selesai dihitung sampai sekarang, bukan nol. Perintah
     * kerja yang menganggur tiga minggu adalah waktu henti tiga minggu;
     * menghitungnya nol sampai ditutup membuat ketersediaan terlihat
     * paling bagus justru ketika tunggakannya paling menumpuk.
     */
    public function jamHenti(): float
    {
        if (!$this->dilaporkan_pada) return 0.0;

        $akhir = $this->selesai_pada ?: ($this->terbuka() ? now() : null);

        return $akhir ? max(0.0, $this->dilaporkan_pada->floatDiffInHours($akhir)) : 0.0;
    }

    /** Jam pengerjaan: sejak mulai sampai selesai. */
    public function jamPerbaikan(): float
    {
        if (!$this->mulai_pada) return 0.0;

        $akhir = $this->selesai_pada ?: ($this->terbuka() ? now() : null);

        return $akhir ? max(0.0, $this->mulai_pada->floatDiffInHours($akhir)) : 0.0;
    }

    /** Jam yang habis menunggu sebelum pengerjaan dimulai. */
    public function jamMenunggu(): float
    {
        return max(0.0, $this->jamHenti() - $this->jamPerbaikan());
    }

    public function totalBiaya(): float
    {
        return $this->biaya + $this->parts->sum(fn (WorkOrderPart $p) => $p->subtotal());
    }

    /* ---------- verifikasi penutupan ---------- */

    /**
     * Menutup perintah kerja menuliskan angka ke MTTR, ketersediaan, dan
     * biaya per ton. Verifikasi menyatakan angka itu sudah diperiksa.
     *
     * Yang diverifikasi terkunci dari penyuntingan — mengubah jamnya
     * setelah diperiksa berarti menggeser angka yang sudah masuk laporan
     * tanpa jejak.
     */
    public static function booted(): void
    {
        static::updating(function (self $wo) {
            if ($wo->getOriginal('diverifikasi_pada') === null) return;

            $lain = array_diff(array_keys($wo->getDirty()), [
                'diverifikasi_oleh', 'diverifikasi_pada', 'updated_at',
            ]);

            if ($lain !== []) {
                throw new \RuntimeException(
                    'Perintah kerja yang sudah diverifikasi tidak dapat diubah. '.
                    'Batalkan verifikasinya lebih dulu.'
                );
            }
        });
    }

    public function sudahDiverifikasi(): bool
    {
        return $this->diverifikasi_pada !== null;
    }

    public function menungguVerifikasi(): bool
    {
        return $this->status === 'selesai' && !$this->sudahDiverifikasi();
    }

    /**
     * Yang menutup tidak memverifikasi pekerjaannya sendiri.
     *
     * Aturannya sama dengan alur tinjauan data produksi, dan alasannya
     * pun sama: tanda tangan yang dibubuhkan oleh orang yang sama dengan
     * pelapornya tidak menambahkan pemeriksaan apa pun.
     */
    public function dapatDiverifikasiOleh(?User $u): bool
    {
        if (!\App\Support\Alur::peninjau($u)) return false;
        if (!$this->menungguVerifikasi()) return false;

        return $this->ditutup_oleh !== $u?->getKey();
    }

    public function verifikasi(?User $u = null): void
    {
        $u ??= auth()->user();

        if (!$this->dapatDiverifikasiOleh($u)) {
            throw new \RuntimeException('Anda tidak berhak memverifikasi perintah kerja ini.');
        }

        $this->forceFill([
            'diverifikasi_oleh' => $u?->getKey(),
            'diverifikasi_pada' => now(),
        ])->save();
    }

    public function batalkanVerifikasi(): void
    {
        $this->forceFill(['diverifikasi_oleh' => null, 'diverifikasi_pada' => null])->save();
    }

    /* ---------- saringan ---------- */

    public function scopeTerbukaSaja(Builder $q): Builder
    {
        return $q->whereIn('status', self::TERBUKA);
    }

    public function scopePeriode(Builder $q, mixed $dari, mixed $sampai): Builder
    {
        return $q->whereBetween('dilaporkan_pada', [$dari, $sampai]);
    }

    /** Yang mendesak lebih dulu: kritikalitas alat, lalu lama menganggur. */
    public function scopeUrutMendesak(Builder $q): Builder
    {
        return $q
            ->orderByRaw("CASE prioritas WHEN 'kritis' THEN 0 WHEN 'tinggi' THEN 1 WHEN 'sedang' THEN 2 ELSE 3 END")
            ->orderBy('dilaporkan_pada');
    }

    public function toView(): array
    {
        return [
            'id'              => $this->id,
            'nomor'           => $this->nomor,
            'jenis'           => $this->jenis,
            'prioritas'       => $this->prioritas,
            'status'          => $this->status,
            'gejala'          => $this->gejala,
            'penyebab'        => $this->penyebab,
            'tindakan'        => $this->tindakan,
            'objek'           => $this->objek?->kode,
            'objekNama'       => $this->objek?->nama,
            'kritikalitas'    => $this->objek?->kritikalitas,
            'dilaporkanPada'  => $this->dilaporkan_pada?->format('Y-m-d H:i'),
            'mulaiPada'       => $this->mulai_pada?->format('Y-m-d H:i'),
            'selesaiPada'     => $this->selesai_pada?->format('Y-m-d H:i'),
            'jamHenti'        => round($this->jamHenti(), 2),
            'jamPerbaikan'    => round($this->jamPerbaikan(), 2),
            'jamMenunggu'     => round($this->jamMenunggu(), 2),
            'biaya'           => $this->totalBiaya(),
            'terbuka'         => $this->terbuka(),

            'verifikasi' => [
                'sudah'         => $this->sudahDiverifikasi(),
                'menunggu'      => $this->menungguVerifikasi(),
                'dapatDiverifikasi' => $this->dapatDiverifikasiOleh(auth()->user()),
                'penutup'       => $this->penutup?->name,
                'pemverifikasi' => $this->pemverifikasi?->name,
                'pada'          => $this->diverifikasi_pada?->format('Y-m-d H:i'),
            ],
        ];
    }
}
