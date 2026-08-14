<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, MorphTo};

/**
 * Tindak lanjut, dipakai bersama seluruh modul.
 *
 * Konservasi sempat memiliki tabel tindak lanjutnya sendiri. Bila Operasi
 * kemudian dibuatkan tabelnya sendiri pula, dan delapan modul berikutnya
 * mengikuti, yang lahir adalah sepuluh skema yang mirip tetapi tidak sama
 * — dan pertanyaan sesederhana "apa saja yang terlambat di seluruh site"
 * berubah menjadi sepuluh kueri yang harus disatukan dengan tangan.
 *
 * `sumber` bersifat morph supaya sebuah tindak lanjut dapat menunjuk baris
 * mana pun yang melahirkannya — catatan operasi, catatan konservasi, dan
 * kelak temuan inspeksi — tanpa menambah kolom setiap kali ada modul baru.
 *
 * `kode_pemicu` menyimpan kode peringatan yang melahirkannya. Itulah yang
 * menutup lingkaran dari Peringatan ke Tindak lanjut: tanpa penanda yang
 * tetap, satu-satunya penaut yang tersedia adalah judul peringatan, dan
 * judul berubah setiap kali kalimatnya diperbaiki.
 */
#[ScopedBy(MilikPerusahaan::class)]
class TindakLanjut extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'tindak_lanjut';

    /**
     * `terlambat` sengaja bukan salah satu status.
     *
     * Sebelumnya ia disimpan, dan karena itu ia basi: baris bertanda
     * "berjalan" yang targetnya lewat sebulan lalu tetap terbaca berjalan
     * sampai ada orang yang menyuntingnya. Peringatan yang menghitung
     * status itu melaporkan kurang, dan kekurangannya persis pada yang
     * paling perlu ditagih. Kini keterlambatan dihitung dari tanggal,
     * sehingga ia tidak dapat tertinggal.
     */
    public const STATUS = ['rencana', 'berjalan', 'selesai', 'batal'];

    public const PRIORITAS = ['rendah', 'sedang', 'tinggi', 'kritis'];

    /** Status yang dianggap masih menuntut pekerjaan. */
    public const TERBUKA = ['rencana', 'berjalan'];

    protected $fillable = [
        'company_id', 'user_id', 'sumber_type', 'sumber_id', 'modul', 'kode_pemicu',
        'judul', 'kategori', 'prioritas', 'status', 'penanggung_jawab',
        'target_selesai', 'selesai_pada', 'uraian',
    ];

    protected function casts(): array
    {
        return [
            'target_selesai' => 'date',
            'selesai_pada'   => 'date',
        ];
    }

    public function sumber(): MorphTo    { return $this->morphTo(); }
    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }

    /* ---------- keadaan ---------- */

    public function terbuka(): bool
    {
        return in_array($this->status, self::TERBUKA, true);
    }

    public function terlambat(): bool
    {
        return $this->terbuka()
            && $this->target_selesai !== null
            && $this->target_selesai->isPast();
    }

    /** Berapa hari terlambat; 0 bila belum atau tidak terlambat. */
    public function hariTerlambat(): int
    {
        return $this->terlambat() ? $this->target_selesai->diffInDays(now()) : 0;
    }

    /**
     * Label yang menggabungkan status dengan keterlambatan.
     *
     * Keterlambatan ditampilkan sebagai keterangan atas status, bukan
     * sebagai pengganti — "berjalan, terlambat 12 hari" menyebut dua hal
     * yang keduanya perlu diketahui, sementara "terlambat" saja
     * menyembunyikan apakah pekerjaannya sudah dimulai.
     */
    public function label(): string
    {
        $dasar = ucfirst($this->status);

        return $this->terlambat()
            ? $dasar.', terlambat '.$this->hariTerlambat().' hari'
            : $dasar;
    }

    /* ---------- saringan ---------- */

    public function scopeModul(Builder $q, string $modul): Builder
    {
        return $q->where('modul', $modul);
    }

    public function scopeTerbukaSaja(Builder $q): Builder
    {
        return $q->whereIn('status', self::TERBUKA);
    }

    public function scopeTerlambatSaja(Builder $q): Builder
    {
        return $q->whereIn('status', self::TERBUKA)
            ->whereNotNull('target_selesai')
            ->whereDate('target_selesai', '<', now()->toDateString());
    }

    /**
     * Urutan tampil: yang terlambat lebih dulu, lalu prioritas tertinggi,
     * lalu yang target selesainya paling dekat.
     */
    public function scopeUrutMendesak(Builder $q): Builder
    {
        return $q
            ->orderByRaw(
                "CASE WHEN status IN ('rencana','berjalan') AND target_selesai IS NOT NULL AND target_selesai < ? THEN 0 ELSE 1 END",
                [now()->toDateString()]
            )
            ->orderByRaw("CASE prioritas WHEN 'kritis' THEN 0 WHEN 'tinggi' THEN 1 WHEN 'sedang' THEN 2 ELSE 3 END")
            ->orderByRaw('target_selesai IS NULL')
            ->orderBy('target_selesai');
    }

    public function toView(): array
    {
        return [
            'id'               => $this->id,
            'modul'            => $this->modul,
            'kodePemicu'       => $this->kode_pemicu,
            'judul'            => $this->judul,
            'kategori'         => $this->kategori,
            'prioritas'        => $this->prioritas,
            'status'           => $this->status,
            'statusLabel'      => $this->label(),
            'terlambat'        => $this->terlambat(),
            'hariTerlambat'    => $this->hariTerlambat(),
            'penanggung_jawab' => $this->penanggung_jawab,
            'target_selesai'   => $this->target_selesai?->toDateString(),
            'selesai_pada'     => $this->selesai_pada?->toDateString(),
            'uraian'           => $this->uraian,
        ];
    }
}
