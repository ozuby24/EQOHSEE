<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Temuan audit SMKP beserta tindakan perbaikannya (CAR).
 */
class SmkpFinding extends Model
{
    use BerindukPerusahaan;

    /** Pemiliknya ada pada induknya; lihat BerindukPerusahaan. */
    protected static string $indukPerusahaan = 'audit';

    protected $table = 'smkp_findings';

    /**
     * Nilai status yang berarti temuan sudah ditutup.
     *
     * Sebuah tetapan, bukan teks yang diketik ulang. Ia sudah tertulis
     * berhuruf besar di lima tempat dan berhuruf kecil di lima tempat
     * lain — dan perbandingan yang salah hurufnya tidak menimbulkan
     * galat, hanya hitungan "sudah selesai" yang selamanya nol.
     */
    public const TUTUP = 'Closed';

    protected $fillable = [
        'audit_id', 'kode_kriteria', 'document_id', 'jenis', 'uraian', 'akar_masalah',
        'tindakan', 'penanggung_jawab', 'target_selesai', 'tanggal_selesai',
        'status', 'verifikasi',
        'respon_diterima', 'respon_manajemen', 'respon_oleh', 'respon_pada',
        'foto_open', 'foto_closed', 'verifikasi_oleh', 'verifikasi_pada',
    ];

    protected function casts(): array
    {
        return [
            'target_selesai'  => 'date',
            'tanggal_selesai' => 'date',
            'respon_pada'     => 'date',
            'verifikasi_pada' => 'date',

            /* Tiga keadaan, bukan dua: belum menjawab, menerima, menolak.
               Boolean biasa akan menjatuhkan "belum menjawab" menjadi
               "menolak" — dan itu menuduh manajemen atas sikap yang
               belum pernah mereka nyatakan. */
            'respon_diterima' => 'boolean',
        ];
    }

    public function audit(): BelongsTo        { return $this->belongsTo(SmkpAudit::class, 'audit_id'); }
    public function document(): BelongsTo     { return $this->belongsTo(Document::class); }

    /** Temuan lewat target penyelesaian dan belum ditutup. */
    public function terlambat(): bool
    {
        return $this->status !== 'Closed'
            && $this->target_selesai
            && $this->target_selesai->isPast();
    }
}
