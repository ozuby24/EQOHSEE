<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Blok;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};
use Illuminate\Support\Carbon;

/**
 * Sekumpulan orang yang bergerak bersama pada satu pola.
 *
 * TANGGAL JANGKARNYA yang membuat polanya dapat dihitung. Dua regu pada
 * pola 14:7 yang sama tetapi berjangkar tujuh hari berselisih akan
 * saling mengisi — yang satu pulang ketika yang lain datang. Tanpa
 * jangkar, seluruh regu libur pada minggu yang sama dan site kosong;
 * itu kesalahan yang baru terlihat setelah tiketnya terbit.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Regu extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'hr_regu';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['mulai' => 'date', 'aktif' => 'boolean'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function pola(): BelongsTo    { return $this->belongsTo(PolaRoster::class, 'pola_roster_id'); }
    public function blok(): BelongsTo    { return $this->belongsTo(Blok::class, 'blok_id'); }

    public function anggota(): HasMany
    {
        return $this->hasMany(ReguAnggota::class, 'regu_id');
    }

    public function roster(): HasMany
    {
        return $this->hasMany(Roster::class, 'regu_id');
    }

    /* ═══════════ perhitungan siklus ═══════════ */

    /**
     * Hari keberapa dalam siklusnya, pada sebuah tanggal. Nol-berbasis.
     *
     * TANGGAL SEBELUM JANGKAR IKUT TERHITUNG, dan itu bukan kelonggaran:
     * roster kerap disusun mundur untuk merekonsiliasi absensi bulan
     * lalu, dan modulo negatif PHP memulangkan bilangan negatif —
     * sehingga hari sebelum jangkar akan jatuh ke indeks yang tidak ada
     * dan seluruhnya terbaca "libur".
     */
    public function hariSiklus(Carbon $tanggal): int
    {
        $pola = $this->pola;

        if (! $pola) return 0;

        $siklus  = $pola->siklus();
        $selisih = self::selisihHari($this->mulai, $tanggal);

        return (($selisih % $siklus) + $siklus) % $siklus;
    }

    /**
     * Selisih hari antara dua tanggal, DIHITUNG DARI UNTAIAN TANGGALNYA.
     *
     * Tidak dari kedua Carbon apa adanya, dan itu perbedaan yang
     * menentukan. Tanggal yang dibaca dari basis data ditafsirkan pada
     * zona aplikasi (UTC); tanggal yang dibuat Waktu::kini() berzona
     * WITA. Keduanya menyebut hari yang SAMA tetapi berselisih delapan
     * jam sebagai saat — sehingga `gt()` dan `diffInDays()` menjawab
     * berdasarkan selisih itu, bukan berdasarkan harinya.
     *
     * Akibatnya diam: anggota regu yang ditambahkan "mulai hari ini"
     * dilewati pada hari pertamanya, sebab jangkarnya terbaca delapan
     * jam sesudah hari yang sedang disusun. Satu hari hilang dari tiap
     * keanggotaan baru, dan tidak ada satu galat pun yang menandainya.
     */
    public static function selisihHari(\DateTimeInterface $dari, \DateTimeInterface $sampai): int
    {
        $a = Carbon::parse($dari->format('Y-m-d'));
        $b = Carbon::parse($sampai->format('Y-m-d'));

        return (int) $a->diffInDays($b, false);
    }

    /** Regu ini bekerja pada tanggal itu menurut polanya. */
    public function bekerjaPada(Carbon $tanggal): bool
    {
        $pola = $this->pola;

        if (! $pola) return false;

        $hari = $this->hariSiklus($tanggal);

        /* Di luar periode kerjanya — pulang. */
        if ($hari >= $pola->hariKerja()) return false;

        /* Libur mingguan DI DALAM periode kerja. Pola sepuluh minggu
           bukan berarti tujuh puluh hari tanpa jeda; tanpa jeda itu ia
           melanggar batas empat belas hari Kepmenakertrans 234/2003
           pada tiap siklusnya, padahal polanya dipakai sungguhan. */
        $per = (int) ($pola->libur_mingguan ?? 0);

        if ($per <= 0) return true;

        return ($hari % 7) < max(1, 7 - $per);
    }

    /**
     * Shift regu ini pada sebuah tanggal.
     *
     * Pola `putar` berganti tiap SIKLUS, bukan tiap hari: seorang yang
     * masuk malam berganti ke siang sesudah pulang cuti, bukan di
     * tengah periode kerjanya. Berganti tiap hari, pekerjanya tidak
     * pernah beradaptasi dan justru itu yang dilarang aturan fatigue.
     */
    public function shiftPada(Carbon $tanggal): ?string
    {
        $pola = $this->pola;

        if (! $pola || ! $this->bekerjaPada($tanggal)) return null;

        if ($pola->shift !== 'putar') return $pola->shift;

        $awal = $this->shift ?: 'siang';

        $siklusKe = (int) floor(self::selisihHari($this->mulai, $tanggal) / $pola->siklus());

        $genap = ((($siklusKe % 2) + 2) % 2) === 0;

        return $genap ? $awal : ($awal === 'siang' ? 'malam' : 'siang');
    }

    /** Anggota yang berlaku pada sebuah tanggal. */
    public function anggotaPada(Carbon $tanggal)
    {
        /* Batas atas dibuat sampai akhir hari: kolomnya bertipe DATE
           tetapi menyimpan "2026-09-12 00:00:00", dan sebagai teks
           "2026-09-12 00:00:00" <= "2026-09-12" bernilai SALAH. */
        return $this->anggota()
            ->where('mulai', '<=', $tanggal->toDateString().' 23:59:59')
            ->where(fn ($q) => $q->whereNull('selesai')
                ->orWhere('selesai', '>=', $tanggal->toDateString()));
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif', true);
    }
}
