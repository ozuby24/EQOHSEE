<?php

namespace App\Models\Miners;

use App\Models\Certificate;
use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\KompetensiJenis;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use App\Support\Waktu;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu sertifikat kompetensi milik satu orang.
 *
 * MASA BERLAKUNYA MELEKAT DI SINI, bukan pada orangnya. Seorang
 * pengawas dapat memegang POP yang berlaku sampai 2028 dan Ahli K3
 * Kebakaran yang habis bulan depan; satu tanggal untuk keduanya
 * membuat salah satunya selalu salah.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Kompetensi extends Model
{
    use BerpemilikPerusahaan;

    /** Ambang pengingat, sama dengan ambang MCU menurut SOP. */
    public const HARI_PERINGATAN = 30;

    protected $table = 'mnr_kompetensi';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tanggal_terbit' => 'date',
            'berlaku_sampai' => 'date',
        ];
    }

    public function company(): BelongsTo     { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
    public function pekerja(): BelongsTo     { return $this->belongsTo(Pekerja::class, 'pekerja_id'); }
    public function jenis(): BelongsTo       { return $this->belongsTo(KompetensiJenis::class, 'kompetensi_jenis_id'); }
    public function sertifikat(): BelongsTo  { return $this->belongsTo(Certificate::class, 'certificate_id'); }

    /**
     * Sisa hari sampai habis, negatif bila sudah lewat.
     *
     * NULL berarti TIDAK BERMASA BERLAKU — sertifikat sistem seperti
     * ISO memang begitu — dan itu berbeda dari "habis hari ini".
     * Diperlakukan sebagai nol, sertifikat tanpa kedaluwarsa akan
     * muncul di daftar yang harus diperbarui setiap hari, selamanya.
     */
    public function sisaHari(): ?int
    {
        return $this->berlaku_sampai === null
            ? null
            : (int) Waktu::kini()->startOfDay()->diffInDays($this->berlaku_sampai, false);
    }

    public function kedaluwarsa(): bool
    {
        $sisa = $this->sisaHari();

        return $sisa !== null && $sisa < 0;
    }

    public function mendekatiHabis(): bool
    {
        $sisa = $this->sisaHari();

        return $sisa !== null && $sisa >= 0 && $sisa <= self::HARI_PERINGATAN;
    }

    public function scopeAkanHabis(Builder $q, int $hari = self::HARI_PERINGATAN): Builder
    {
        /* Dibandingkan sebagai TANGGAL, bukan sebagai waktu.
           Batas berupa Carbon berjam 00:00:00 akan melewatkan baris
           yang terlanjur tersimpan dengan jam — dan MySQL memangkas
           jamnya di tingkat kolom sedangkan SQLite tidak, sehingga
           kueri yang sama menjawab berbeda di server dan di mesin
           penguji. */
        return $q->whereNotNull('berlaku_sampai')
            ->whereBetween('berlaku_sampai', [
                Waktu::kini()->startOfDay()->toDateString(),
                Waktu::kini()->startOfDay()->addDays($hari)->toDateString().' 23:59:59',
            ]);
    }
}
