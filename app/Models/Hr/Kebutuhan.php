<?php

namespace App\Models\Hr;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\{Blok, Jabatan};
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kebutuhan tenaga kerja per area — manpower plan lawan actual.
 *
 * DISIMPAN PER JABATAN, bukan sebagai satu angka per site. Kekurangan
 * dua operator excavator tidak tertutup oleh kelebihan tiga admin, dan
 * satu angka gabungan menyembunyikan persis kekurangan yang
 * menghentikan produksi — lalu layar pemantauan menyatakan "cukup"
 * pada hari front berhenti menggali.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Kebutuhan extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'hr_kebutuhan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['mulai' => 'date', 'selesai' => 'date', 'jumlah' => 'integer'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function blok(): BelongsTo    { return $this->belongsTo(Blok::class, 'blok_id'); }
    public function jabatan(): BelongsTo { return $this->belongsTo(Jabatan::class, 'jabatan_id'); }

    /** Berlaku pada sebuah tanggal — `selesai` kosong berarti terbuka. */
    public function scopePada(Builder $q, string $tanggal): Builder
    {
        return $q->where('mulai', '<=', $tanggal)
            ->where(fn (Builder $w) => $w->whereNull('selesai')->orWhere('selesai', '>=', $tanggal));
    }
}
