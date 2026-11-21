<?php

namespace App\Models\Energi;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\{Company, User};
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Laporan energi/GRK berversi dengan snapshot beku.
 *
 * Setelah terbit, angka laporan dibaca dari snapshot — bukan dihitung ulang
 * — sehingga faktor yang diperbarui tahun depan tidak mengubah laporan yang
 * sudah disampaikan ke regulator.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Laporan extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'energi_laporan';

    public const STATUS = ['draf', 'diajukan', 'disetujui', 'terbit', 'perlu_revisi', 'diganti'];

    protected $fillable = ['company_id', 'templat', 'tahun', 'periode_mulai', 'periode_sampai', 'versi', 'status',
                           'judul', 'nomor_dokumen', 'snapshot', 'hash_snapshot', 'disusun_oleh', 'diajukan_oleh',
                           'diajukan_pada', 'disetujui_oleh', 'disetujui_pada', 'diterbitkan_oleh', 'diterbitkan_pada',
                           'disampaikan_pada', 'bukti_penyampaian', 'pengganti_id', 'catatan'];

    protected function casts(): array
    {
        return ['tahun' => 'integer', 'versi' => 'integer', 'periode_mulai' => 'date', 'periode_sampai' => 'date',
                'snapshot' => 'array', 'diajukan_pada' => 'datetime', 'disetujui_pada' => 'datetime',
                'diterbitkan_pada' => 'datetime', 'disampaikan_pada' => 'date'];
    }

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function penyusun(): BelongsTo  { return $this->belongsTo(User::class, 'disusun_oleh'); }
    public function penyetuju(): BelongsTo { return $this->belongsTo(User::class, 'disetujui_oleh'); }
    public function penerbit(): BelongsTo  { return $this->belongsTo(User::class, 'diterbitkan_oleh'); }
    public function pengganti(): BelongsTo { return $this->belongsTo(self::class, 'pengganti_id'); }

    public function terbit(): bool { return in_array($this->status, ['terbit', 'perlu_revisi', 'diganti'], true); }

    /** Hash SHA-256 isi snapshot — pembuktian bahwa angka laporan tidak berubah sejak terbit. */
    public static function hash(array $snapshot): string
    {
        return hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
    }

    public function utuh(): bool
    {
        return $this->hash_snapshot !== null && $this->snapshot !== null
            && hash_equals($this->hash_snapshot, self::hash($this->snapshot));
    }
}
