<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\{Blok, Pekerja};
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan harian — satu baris per orang per tanggal.
 *
 * DITURUNKAN dari jejaknya lalu direkonsiliasi terhadap roster. Yang
 * disimpan adalah kesimpulannya: jam masuk, jam keluar, berapa jam, dan
 * apakah itu sesuai dengan yang dijadwalkan.
 *
 * "LUAR ROSTER" ADALAH KEADAAN TERSENDIRI, bukan sekadar hadir. Orang
 * yang bekerja pada hari liburnya memang hadir — tetapi jamnya tidak
 * masuk hitungan tunjangan site, ikut menghitung batas empat belas hari
 * berturut-turut, dan hampir selalu berarti ada lembur yang belum
 * diperintahkan. Digabung dengan "hadir", ketiganya hilang sekaligus.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Absensi extends Model
{
    use BerpemilikPerusahaan;

    public const KEADAAN = [
        'hadir'        => 'Hadir',
        'terlambat'    => 'Terlambat',
        'belum_pulang' => 'Belum tap pulang',
        'absen'        => 'Absen',
        'luar_roster'  => 'Di luar roster',
    ];

    /** Keadaan yang berarti orangnya benar-benar bekerja hari itu. */
    public const BEKERJA = ['hadir', 'terlambat', 'belum_pulang', 'luar_roster'];

    protected $table = 'hr_absensi';

    protected $guarded = ['id'];

    protected $attributes = ['keadaan' => 'absen', 'jam' => 0, 'dikoreksi' => false];

    protected function casts(): array
    {
        return [
            'tanggal'        => 'date',
            'masuk'          => 'datetime',
            'keluar'         => 'datetime',
            'jam'            => 'float',
            'telat_menit'    => 'integer',
            'luring'         => 'boolean',
            'dalam_area'     => 'boolean',
            'dikoreksi'      => 'boolean',
            'dikoreksi_pada' => 'datetime',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function pekerja(): BelongsTo { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }
    public function roster(): BelongsTo  { return $this->belongsTo(Roster::class, 'roster_id'); }
    public function blok(): BelongsTo    { return $this->belongsTo(Blok::class, 'blok_id'); }
    public function pengoreksi(): BelongsTo { return $this->belongsTo(User::class, 'dikoreksi_oleh'); }

    public function bekerja(): bool
    {
        return in_array($this->keadaan, self::BEKERJA, true);
    }

    public function scopeBekerja(Builder $q): Builder
    {
        return $q->whereIn('keadaan', self::BEKERJA);
    }

    /**
     * Rentang tanggal, INKLUSIF pada kedua ujungnya.
     *
     * Alasannya sama persis dengan Roster::scopeAntara: kolomnya
     * bertipe DATE tetapi menyimpan "2026-09-22 00:00:00", dan sebagai
     * perbandingan teks "2026-09-22 00:00:00" <= "2026-09-22" bernilai
     * SALAH. Hari terakhir rekap hilang tanpa satu galat pun.
     */
    public function scopeAntara(Builder $q, string $dari, string $sampai): Builder
    {
        return $q->where('tanggal', '>=', $dari)
            ->where('tanggal', '<=', $sampai.' 23:59:59');
    }
}
