<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rencana Tindak Perbaikan satu kriteria Audit Kinerja Lingkungan.
 * Terbit otomatis dari nilai verifikasi ≤ 2 — lihat App\Support\RtpLingkungan.
 *
 * Tidak berpemilik perusahaan sendiri: batasnya diwarisi dari EnvAudit
 * (Scopes\MilikPerusahaan). Setiap jalan masuk mencari baris ini LEWAT
 * auditnya, sehingga RTP perusahaan lain tidak pernah dapat ditemukan.
 */
class EnvAuditRtp extends Model
{
    protected $table = 'env_audit_rtps';

    public const STATUS = ['buka' => 'Terbuka', 'proses' => 'Proses', 'selesai' => 'Selesai'];

    public const MENUNGGU  = 'menunggu';
    public const DISETUJUI = 'disetujui';
    public const DITOLAK   = 'ditolak';

    protected $fillable = [
        'audit_id', 'kode', 'nilai', 'temuan', 'rencana', 'pic', 'target_tanggal',
        'status', 'tanggal_selesai', 'catatan_penutup', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'nilai'             => 'integer',
            'target_tanggal'    => 'date:Y-m-d',
            'tanggal_selesai'   => 'date:Y-m-d',
            'bukti'             => 'array',
            'bukti_nama'        => 'array',
            'diverifikasi_pada' => 'datetime',
            'diajukan_pada'     => 'datetime',
        ];
    }

    public function audit(): BelongsTo { return $this->belongsTo(EnvAudit::class, 'audit_id'); }

    /** Lewat target selesai dan belum ditutup — menurut tanggal setempat. */
    public function terlambat(): bool
    {
        return $this->status !== 'selesai'
            && $this->target_tanggal
            && $this->target_tanggal->toDateString() < \App\Support\Waktu::kini()->toDateString();
    }

    /** Belum disentuh auditee: aman dihapus bila temuannya gugur. */
    public function kosong(): bool
    {
        return trim((string) $this->rencana) === '' && trim((string) $this->pic) === ''
            && !$this->target_tanggal && !count((array) $this->bukti);
    }
}
