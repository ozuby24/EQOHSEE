<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Berkas bukti yang melekat pada satu butir kriteria audit SMKP.
 *
 * Berdampingan dengan kolom teks `bukti` di dalam `smkp_audits.hasil`,
 * tidak menggantikannya: yang satu menjawab "bukti apa" — nomor
 * dokumen, siapa yang diwawancarai — yang ini menjawab "mana
 * buktinya". Berkas audit yang diminta Inspektur Tambang menuntut
 * keduanya.
 *
 * Batas perusahaannya menumpang induknya lewat BerindukPerusahaan.
 * Itu bukan kerapian: rute penyaji berkas mengikat BARIS INI langsung,
 * tanpa menyebut auditnya sama sekali, sehingga tanpa scope ini seluruh
 * bukti audit perusahaan lain terbaca oleh siapa pun yang menebak
 * nomornya.
 */
class SmkpBukti extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'audit';

    protected $table = 'smkp_bukti';

    protected $fillable = [
        'audit_id', 'kode', 'file_path', 'file_name', 'file_size',
        'mime', 'catatan', 'user_id',
    ];

    protected function casts(): array
    {
        return ['file_size' => 'integer'];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(SmkpAudit::class, 'audit_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
