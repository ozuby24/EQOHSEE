<?php

namespace App\Models\Meranti;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log aktivitas MERANTI — tambah, ubah, hapus, impor.
 *
 * Riwayat dari lembar Google Sheets ikut dimuat dengan `pengguna` berisi
 * nama akun lamanya; baris yang dicatat di EQOHSEE membawa user_id.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Log extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'meranti_log';

    protected $fillable = ['company_id', 'user_id', 'waktu', 'pengguna', 'aksi', 'modul', 'detail', 'asal_ref'];

    protected function casts(): array
    {
        return ['waktu' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
