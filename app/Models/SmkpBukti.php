<?php

namespace App\Models;

use App\Support\BerkasUnggah;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu berkas bukti kelengkapan untuk satu butir kriteria audit. Tidak
 * bercakupan sendiri: selalu dibaca lewat audit induknya, yang membawa
 * batas perusahaan pengguna.
 */
class SmkpBukti extends Model
{
    protected $table = 'smkp_bukti';

    /** Folder berkas di disk privat 'local'; satu folder per audit. */
    public const FOLDER = 'bukti-audit';

    public const MENUNGGU = 'menunggu';
    public const DITERIMA = 'diterima';
    public const DITOLAK  = 'ditolak';

    public const STATUS = [
        self::MENUNGGU => 'Menunggu verifikasi',
        self::DITERIMA => 'Diterima',
        self::DITOLAK  => 'Perlu perbaikan',
    ];

    protected $fillable = [
        'audit_id', 'kode', 'user_id', 'path', 'nama', 'mime', 'ukuran', 'keterangan',
        'status', 'catatan', 'diverifikasi_oleh', 'diverifikasi_pada',
    ];

    /** Kunci asing bertipe bilangan: pencocokan audit & pengunggah memakai perbandingan ketat. */
    protected function casts(): array
    {
        return [
            'audit_id' => 'integer', 'user_id' => 'integer', 'diverifikasi_oleh' => 'integer',
            'ukuran' => 'integer', 'diverifikasi_pada' => 'datetime',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SmkpAudit::class, 'audit_id');
    }

    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function verifikator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }

    public static function folder(int $auditId): string
    {
        return self::FOLDER.'/'.$auditId;
    }

    /** Sama dengan jenis yang boleh tampil langsung saat diunduh (?lihat). */
    public function gambar(): bool
    {
        return in_array($this->mime, BerkasUnggah::PRATINJAU, true);
    }
}
