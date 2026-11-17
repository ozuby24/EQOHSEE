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
 * ATURAN SAFE TRACK (extendcardsController + workflowextendsController):
 *
 *   · hanya kartu yang terbit atau expired, masih aktif, dan tidak punya
 *     perpanjangan lain yang sedang berjalan yang dapat diperpanjang;
 *   · begitu DOKTER menyetujui, tanggal periksa dan masa berlaku MCU
 *     kartunya DITULIS BALIK ke register MCU — penilaian medisnya memang
 *     wewenang dokter, bukan KTT;
 *   · tanggal habis kartunya baru berpindah sesudah SELURUH langkah
 *     disetujui (HSE lalu KTT), bersumber `perpanjangan`; kartu yang
 *     sempat ditandai expired kembali terbit;
 *   · setiap perpanjangan yang disetujui dicatat pada riwayatnya.
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

    /** Status yang berarti pengajuannya masih berjalan. */
    public const BERJALAN = ['draf', 'diajukan', 'diperiksa', 'ohse', 'ktt'];

    protected static function booted(): void
    {
        static::created(function (self $p) {
            if (blank($p->no_registrasi)) {
                $p->forceFill(['no_registrasi' => 'PMP00'.$p->getKey()])->saveQuietly();
            }
        });
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function permit(): BelongsTo  { return $this->belongsTo(Permit::class, 'permit_id'); }

    /**
     * Sesudah satu langkah disetujui. Langkah DOKTER menulis balik
     * tanggal periksa dan masa berlaku MCU baru ke register MCU kartunya.
     */
    public function setelahLangkahDisetujui(Alur $langkah, \App\Models\User $oleh): void
    {
        if ($langkah->peran !== 'dokter') return;

        $mcu = $this->permit?->mcuOrang;

        if (! $mcu || ! $this->berlaku_baru) return;

        $mcu->forceFill([
            'tanggal_periksa' => $this->tanggal_mcu ?? $mcu->tanggal_periksa,
            'berlaku_sampai'  => $this->berlaku_baru,
            'tanggal_berikut' => $this->berlaku_baru,
        ])->save();
    }

    /** Sesudah seluruh langkah disetujui — tanggal kartunya berpindah. */
    public function setelahAlurTuntas(\App\Models\User $oleh): void
    {
        $permit = $this->permit;

        if (! $permit || ! $this->berlaku_baru) return;

        /* Keadaan lama diambil SEBELUM ditimpa — sesudah baris di bawah,
           tanggal lamanya tidak terbaca lagi dari mana pun. Tanggal
           kartunya dari KOLOM: pengajuan ini sudah berstatus selesai,
           sehingga habisEfektif() kini membaca MCU yang ditulis dokter —
           yaitu tanggal barunya. Kolom itulah yang ditahan selama
           perpanjangan berjalan. */
        $lama = RiwayatPerpanjangan::keadaanPermit($permit);

        if ($permit->berlaku_sampai) $lama['berlaku'] = $permit->berlaku_sampai->toDateString();

        $permit->forceFill([
            'berlaku_sampai' => $this->berlaku_baru,
            'sumber_berlaku' => 'perpanjangan',
            'status'         => $permit->status === 'kedaluwarsa' ? 'terbit' : $permit->status,
        ])->save();

        RiwayatPerpanjangan::catat('permit', (int) $permit->id, $lama,
            RiwayatPerpanjangan::keadaanPermit($permit->fresh()), (int) $this->id, $oleh->getKey());
    }

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
