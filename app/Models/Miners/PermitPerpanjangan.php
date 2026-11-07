<?php

namespace App\Models\Miners;

use App\Models\Company;
use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Miners\Concerns\PunyaAlur;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perpanjangan Mine Permit — `extendcards` Safe Track.
 *
 * BUKAN penerbitan baru: kartunya tetap kartu yang sama, yang berubah
 * hanya tanggal habisnya. Karena perpanjangan mengklaim ada pemeriksaan
 * MCU baru di baliknya, rantainya lewat DOKTER lebih dulu, lalu HSE dan
 * KTT — rantai `perpanjangan_permit` pada Alur::LANGKAH.
 *
 * Tabelnya disiapkan pada tahap dasar; layar dan aturan penerapannya
 * (penulisan balik ke MCU, riwayat) dikerjakan pada paket Mine Permit.
 */
#[ScopedBy(MilikPerusahaan::class)]
class PermitPerpanjangan extends Model
{
    use BerpemilikPerusahaan;
    use PunyaAlur;

    protected static string $jenisDokumen = 'perpanjangan_permit';

    public const STATUS = [
        'draf'      => 'Draf',
        'diajukan'  => 'Diajukan',
        'diperiksa' => 'Pemeriksaan Dokter',
        'ohse'      => 'Verifikasi HSE',
        'ktt'       => 'Menunggu Pengesahan KTT',
        'selesai'   => 'Selesai',
        'ditolak'   => 'Ditolak',
    ];

    protected $table = 'mnr_permit_perpanjangan';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draf'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'tanggal_mcu' => 'date', 'berlaku_baru' => 'date'];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function permit(): BelongsTo  { return $this->belongsTo(Permit::class, 'permit_id'); }

    /** IUP penerbit ikut kartunya. */
    public function iupPenerbitAwal(): ?int
    {
        $iup = $this->permit_id
            ? Permit::withoutGlobalScopes()->whereKey($this->permit_id)->value('iup_id')
            : null;

        return $iup ? (int) $iup : \App\Support\Miners\Penyetuju::iupDari(
            $this->company_id ?? auth()->user()?->company_id);
    }
}
