<?php

namespace App\Models\Miners;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasManyThrough};

/**
 * Satu orang, dan identitasnya berhenti di sini.
 *
 * Di Project1 tabel ini bernama `inductions` — nama yang menyesatkan
 * sejak awal, sebab isinya bukan induksi melainkan orangnya; induksinya
 * sendiri tercatat pada rangkaian tabel yang lain. Akibatnya bukan
 * sekadar nama yang salah: setiap kueri yang mencari "berapa orang
 * yang sudah diinduksi" menghitung SELURUH pekerja, sebab tiap orang
 * punya satu baris di sana sejak ia didaftarkan.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Pekerja extends Model
{
    use BerpemilikPerusahaan;

    public const STATUS = [
        'aktif'    => 'Aktif',
        'resign'   => 'Resign',
        'nonaktif' => 'Tidak Aktif',
    ];

    public const STATUS_KERJA = [
        'karyawan' => 'Karyawan Tetap',
        'kontrak'  => 'Kontrak',
        'harian'   => 'Harian',
        'tamu'     => 'Tamu / Visitor',
    ];

    /** Golongan darah, mengikuti pilihan pada formulir Project1. */
    public const GOL_DARAH = ['A', 'B', 'AB', 'O'];

    protected $table = 'mnr_pekerja';

    protected $guarded = ['id'];

    /**
     * Status awal ikut ada DI MEMORI, bukan hanya di basis data.
     *
     * Default kolom baru terbaca sesudah barisnya dimuat ulang, sehingga
     * baris yang baru dibuat lalu langsung digambar punya `status`
     * bernilai null — dan lencana statusnya menggambar kotak kosong
     * tanpa satu galat pun.
     */
    protected $attributes = ['status' => 'aktif'];

    protected function casts(): array
    {
        return [
            'tanggal_lahir'   => 'date',
            'tanggal_masuk'   => 'date',
            'tanggal_resign'  => 'date',
        ];
    }

    /* ═══════════ relasi ═══════════ */

    public function company(): BelongsTo      { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo         { return $this->belongsTo(User::class); }
    public function departemen(): BelongsTo   { return $this->belongsTo(Departemen::class, 'departemen_id'); }
    public function jabatan(): BelongsTo      { return $this->belongsTo(Jabatan::class, 'jabatan_id'); }
    public function subkontraktor(): BelongsTo{ return $this->belongsTo(Subkontraktor::class, 'subkontraktor_id'); }
    public function blok(): BelongsTo         { return $this->belongsTo(Blok::class, 'blok_id'); }
    public function subBlok(): BelongsTo      { return $this->belongsTo(SubBlok::class, 'sub_blok_id'); }

    public function mcu(): HasMany
    {
        return $this->hasMany(McuOrang::class, 'pekerja_id')->orderByDesc('tanggal_periksa');
    }

    public function induksi(): HasMany
    {
        return $this->hasMany(InduksiOrang::class, 'pekerja_id')->orderByDesc('tanggal_induksi');
    }

    public function permit(): HasMany
    {
        return $this->hasMany(Permit::class, 'pekerja_id')->orderByDesc('tanggal');
    }

    public function simper(): HasMany
    {
        return $this->hasMany(Simper::class, 'pekerja_id')->orderByDesc('tanggal');
    }

    /* ═══════════ keadaan ═══════════ */

    public function aktif(): bool
    {
        return $this->status === 'aktif';
    }

    /**
     * Usia pada sebuah tanggal, atau hari ini.
     *
     * SOP membatasi usia operator — 18–50 tahun untuk kendaraan ringan,
     * 21–50 untuk alat berat — dan batas itu diperiksa pada tanggal
     * PENGAJUAN, bukan hari ini. Yang berulang tahun ke-51 sesudah
     * kartunya terbit tidak kehilangan kartunya di tengah masa berlaku.
     */
    public function usia(?\DateTimeInterface $pada = null): ?int
    {
        /* `diffInYears` menjawab PECAHAN sejak Carbon 3 — 36.52, bukan
           36 — dan PHP 8.4 memperingatkan setiap penyempitan diam-diam
           ke int. Dibulatkan ke bawah dengan tegas: usia 36 tahun 6
           bulan adalah 36 tahun, bukan 37, dan pembulatan ke atas akan
           menolak operator yang baru berusia 50 tahun 6 bulan padahal
           batas SOP-nya 50. */
        $lahir = $this->tanggal_lahir;

        return $lahir === null ? null : (int) floor($lahir->diffInYears($pada ?? now()));
    }

    /* ═══════════ kueri ═══════════ */

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('status', 'aktif');
    }

    public function scopeCari(Builder $q, ?string $kata): Builder
    {
        return $q->when($kata, fn (Builder $w, string $kata) => $w->where(function (Builder $c) use ($kata) {
            $c->where('nama', 'like', "%{$kata}%")
              ->orWhere('nik', 'like', "%{$kata}%")
              ->orWhere('no_induk', 'like', "%{$kata}%")
              ->orWhere('no_registrasi', 'like', "%{$kata}%");
        }));
    }
}
