<?php

namespace App\Models;

use App\Models\Scopes\IndukAuditTerjangkau;
use App\Support\Smkp;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/** Temuan audit SMKP beserta tindakan perbaikannya (CAR). */
#[ScopedBy(IndukAuditTerjangkau::class)]
class SmkpFinding extends Model
{
    protected $table = 'smkp_findings';

    public const STATUS = ['Open', 'In Progress', 'Closed'];

    /** Kategori ketidaksesuaian pada Formulir Ketidaksesuaian & Tindak Lanjut. */
    public const KATEGORI = ['kritikal' => 'Kritikal', 'mayor' => 'Mayor', 'minor' => 'Minor'];

    protected $fillable = [
        'audit_id', 'nomor', 'kode_kriteria', 'jenis', 'uraian', 'lokasi', 'divisi', 'departemen', 'bukti', 'deskripsi',
        'akar_masalah', 'tindakan', 'bukti_perbaikan', 'penanggung_jawab', 'target_selesai',
        'tanggal_selesai', 'status', 'verifikasi', 'nomor_tindak_lanjut',
        // Tautan lintas modul EQOHSEE: dokumen terkendali sebagai bukti
        // (tidak ditampilkan antarmuka audit, dipertahankan agar tautan
        // yang sudah ada tidak putus).
        'document_id',
    ];

    protected function casts(): array
    {
        return [
            'target_selesai'  => 'date:Y-m-d',
            'tanggal_selesai' => 'date:Y-m-d',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SmkpAudit::class, 'audit_id');
    }

    /** Dokumen terkendali yang ditautkan sebagai bukti (modul Dokumen EQOHSEE). */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /** Kiriman tindak lanjut auditi, terbaru lebih dulu. */
    public function tindakLanjut(): HasMany
    {
        return $this->hasMany(SmkpTindakLanjut::class, 'finding_id')->latest('id');
    }

    /** Pengingat email batas waktu tindak lanjut yang sudah diproses. */
    public function pengingat(): HasMany
    {
        return $this->hasMany(SmkpPengingat::class, 'finding_id');
    }

    /** Nomor tampilan: nomor yang ditetapkan auditor, atau KTS-urutan. */
    public function nomorTampil(int $urutan): string
    {
        return trim((string) $this->nomor) ?: sprintf('KTS-%02d', $urutan);
    }

    /** Urutan tampil: Mayor lalu Minor, masing-masing urut sub-elemen/sub-sub-elemen acuan. */
    public static function banding(self $a, self $b): int
    {
        return [...Smkp::kunciUrutTemuan((string) $a->jenis, (string) $a->kode_kriteria), $a->id]
           <=> [...Smkp::kunciUrutTemuan((string) $b->jenis, (string) $b->kode_kriteria), $b->id];
    }

    public function labelKategori(): string
    {
        return self::KATEGORI[$this->jenis] ?? ucfirst((string) $this->jenis);
    }

    /** Lewat target penyelesaian dan belum ditutup. */
    public function terlambat(): bool
    {
        // Menurut tanggal SETEMPAT (App\Support\Waktu): EQOHSEE berjalan UTC,
        // dan "lewat target" pada pukul 00.00–08.00 WITA tidak boleh
        // tertinggal sehari dari kalender orang yang membacanya.
        return $this->status !== 'Closed'
            && $this->target_selesai
            && $this->target_selesai->toDateString() < \App\Support\Waktu::kini()->toDateString();
    }
}
