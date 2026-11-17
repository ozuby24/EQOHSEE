<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Pekerja;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Surat perintah lembur, beserta hasil hitungannya.
 *
 * NILAINYA DISIMPAN, BUKAN DIHITUNG ULANG SAAT DIBACA. Upah naik — dan
 * lembur bulan lalu yang sudah dibayar ikut berubah nilainya pada
 * layar, sehingga slip gaji yang sudah diterima tidak lagi cocok
 * dengan apa yang ditampilkan sistem. Yang disengketakan pekerja
 * adalah angka di slipnya, dan angka itu harus dapat ditunjukkan
 * kembali persis seperti saat dibayarkan.
 *
 * RINCIAN FAKTORNYA IKUT DISIMPAN, bukan hanya jumlahnya. Sengketa
 * upah lembur selalu berbentuk "kenapa angkanya segini" — dan
 * jawabannya adalah berapa jam dikalikan faktor berapa.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Lembur extends Model
{
    use BerpemilikPerusahaan;

    public const STATUS = [
        'menunggu'   => 'Menunggu persetujuan',
        'disetujui'  => 'Disetujui',
        'ditolak'    => 'Ditolak',
        'dibatalkan' => 'Dibatalkan',
    ];

    public const JENIS_HARI = ['kerja' => 'Hari kerja', 'libur' => 'Hari libur'];

    protected $table = 'hr_lembur';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'menunggu', 'jenis_hari' => 'kerja', 'hari_seminggu' => 6];

    protected function casts(): array
    {
        return [
            'tanggal'       => 'date',
            'jam'           => 'float',
            'upah_sebulan'  => 'float',
            'upah_sejam'    => 'float',
            'dasar_persen'  => 'integer',
            'hari_seminggu' => 'integer',
            'rincian'       => 'array',
            'nilai'         => 'float',
            'diajukan_pada' => 'datetime',
            'ditindak_pada' => 'datetime',
        ];
    }

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function pekerja(): BelongsTo  { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }
    public function absensi(): BelongsTo  { return $this->belongsTo(Absensi::class, 'absensi_id'); }
    public function pengaju(): BelongsTo  { return $this->belongsTo(User::class, 'diajukan_oleh'); }
    public function penindak(): BelongsTo { return $this->belongsTo(User::class, 'ditindak_oleh'); }

    public function menunggu(): bool
    {
        return $this->status === 'menunggu';
    }

    public function scopeMenunggu(Builder $q): Builder
    {
        return $q->where('status', 'menunggu');
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
