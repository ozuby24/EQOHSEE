<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Pola kerja bergilir — 14:7, 10:2 minggu, 4:1, dan seterusnya.
 *
 * SATUANNYA DISIMPAN, TIDAK DISIMPULKAN DARI ANGKANYA. "14:7" berarti
 * hari; "10:2" pada sebagian entitas berarti MINGGU — sepuluh minggu
 * di site, dua minggu pulang. Ditebak dari besar angkanya, pola 10:2
 * minggu dihitung sebagai sepuluh hari dan pekerjanya dipulangkan tujuh
 * puluh hari terlalu cepat, dengan tiket yang sudah terbit.
 */
#[ScopedBy(MilikPerusahaan::class)]
class PolaRoster extends Model
{
    use BerpemilikPerusahaan;

    public const SATUAN = ['hari' => 'Hari', 'minggu' => 'Minggu'];

    public const SHIFT = [
        'siang' => 'Siang',
        'malam' => 'Malam',
        'putar' => 'Bergantian tiap siklus',
    ];

    protected $table = 'hr_pola_roster';

    protected $guarded = ['id'];

    protected $attributes = ['satuan' => 'hari', 'shift' => 'siang', 'jam' => 11];

    /** Jam mulai shift bila polanya belum menyebutkan sendiri. */
    public const MULAI_BAWAAN = ['siang' => '07:00', 'malam' => '19:00'];

    /** Toleransi keterlambatan bila polanya tidak menyebutkan. */
    public const TOLERANSI_BAWAAN = 15;

    protected function casts(): array
    {
        return [
            'kerja' => 'integer',
            'libur' => 'integer',
            'jam'   => 'integer',
            'libur_mingguan' => 'integer',
            'toleransi_menit' => 'integer',
            'aktif' => 'boolean',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }

    public function regu(): HasMany
    {
        return $this->hasMany(Regu::class, 'pola_roster_id');
    }

    /* ═══════════ siklus ═══════════ */

    /** Berapa hari satu bagian kerja berlangsung. */
    public function hariKerja(): int
    {
        return $this->satuan === 'minggu' ? $this->kerja * 7 : $this->kerja;
    }

    /** Berapa hari satu bagian libur berlangsung. */
    public function hariLibur(): int
    {
        return $this->satuan === 'minggu' ? $this->libur * 7 : $this->libur;
    }

    /**
     * Panjang satu siklus penuh, dalam hari.
     *
     * NOL DIJAGA DI SINI, bukan di pemanggilnya. Pola yang kerja dan
     * liburnya sama-sama nol menghasilkan pembagian dengan nol pada
     * tiap perhitungan keadaan — dan yang memasukkannya hanyalah
     * formulir yang dikosongkan, bukan keadaan yang mustahil.
     */
    public function siklus(): int
    {
        return max(1, $this->hariKerja() + $this->hariLibur());
    }

    /**
     * Hari libur di dalam periode kerja, sepanjang satu periode.
     *
     * Satu per tujuh hari bagi pola mingguan yang biasa. Nol berarti
     * memang tanpa jeda — pola 14:7 bekerja empat belas hari penuh,
     * dan itu sah selama tidak melampaui empat belas.
     */
    public function liburDalamKerja(): int
    {
        $per = (int) ($this->libur_mingguan ?? 0);

        return $per <= 0 ? 0 : intdiv($this->hariKerja(), 7) * $per;
    }

    /**
     * Hari kerja SESUNGGUHNYA dalam satu periode.
     *
     * Berbeda dari hariKerja(), yang menghitung panjang periodenya —
     * termasuk libur mingguan di dalamnya. Dipakai menghitung jam,
     * sebab hari libur tidak menyumbang jam.
     */
    public function hariKerjaBersih(): int
    {
        return max(0, $this->hariKerja() - $this->liburDalamKerja());
    }

    /**
     * Hari kerja berturut-turut terpanjang dalam pola ini.
     *
     * INILAH yang dibandingkan dengan batas empat belas hari, bukan
     * panjang periodenya. Pola sepuluh minggu berlibur sekali seminggu
     * bekerja paling lama ENAM hari berturut-turut — sah — sedangkan
     * panjang periodenya tujuh puluh hari. Dibandingkan dengan panjang
     * periode, pola yang dipakai sungguhan di lapangan ditandai
     * melanggar pada tiap siklusnya.
     */
    public function maksBeruntun(): int
    {
        $per = (int) ($this->libur_mingguan ?? 0);

        if ($per <= 0 || $this->hariKerja() < 7) return $this->hariKerja();

        return max(1, 7 - $per);
    }

    /**
     * Jumlah jam kerja dalam satu siklus penuh.
     *
     * Dipakai memeriksa batas 40 jam seminggu: pola 14:7 berjam 11
     * menghasilkan 154 jam per 21 hari — setara 51,3 jam seminggu, di
     * atas batas umum UU 13/2003 dan justru karena itu sektor ESDM
     * punya pengecualiannya sendiri.
     */
    public function jamPerSiklus(): int
    {
        return $this->hariKerjaBersih() * $this->jam;
    }

    /**
     * Jam mulai shift, sebagai "HH:MM".
     *
     * Bawaan dipakai bila polanya belum menyebutkan sendiri — dan itu
     * keadaan yang biasa, sebab kolomnya baru ditambahkan sesudah pola
     * bawaannya terpasang. Memulangkan null, seluruh keterlambatan
     * terhitung nol dan layar absensi menyatakan tidak ada yang
     * terlambat pada hari mana pun.
     */
    public function mulaiShift(?string $shift): string
    {
        $shift = $shift === 'malam' ? 'malam' : 'siang';

        $kolom = $shift === 'malam' ? $this->mulai_malam : $this->mulai_siang;

        if ($kolom === null || $kolom === '') return self::MULAI_BAWAAN[$shift];

        /* Kolom TIME terbaca sebagai "07:00:00"; yang dipakai hanya jam
           dan menitnya. */
        return substr((string) $kolom, 0, 5);
    }

    public function toleransi(): int
    {
        $n = $this->toleransi_menit;

        return $n === null ? self::TOLERANSI_BAWAAN : (int) $n;
    }

    public function scopeTerpakai(Builder $q): Builder
    {
        return $q->where('aktif', true)->orderBy('urutan')->orderBy('kode');
    }

    /** @return array<int,string> id => label, siap menjadi daftar pilih. */
    public static function pilihan(): array
    {
        return static::query()->terpakai()->get()
            ->mapWithKeys(fn (self $p) => [$p->id => $p->kode.' — '.$p->nama])
            ->all();
    }
}
