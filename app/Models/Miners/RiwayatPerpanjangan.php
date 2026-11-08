<?php

namespace App\Models\Miners;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jejak satu perpanjangan Mine Permit atau SIMPER — `riwayat_perpanjangans`
 * Safe Track: tanggal habis lama dan baru, beserta salinan data sebelum
 * dan sesudahnya.
 *
 * Dibaca selalu MELALUI dokumennya (permit/SIMPER yang sudah tersaring
 * perusahaannya), karena itu tidak memakai scope perusahaan sendiri.
 */
class RiwayatPerpanjangan extends Model
{
    public const JENIS = ['permit' => 'Mine Permit', 'simper' => 'SIMPER'];

    protected $table = 'mnr_riwayat_perpanjangan';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'berlaku_lama' => 'date',
            'berlaku_baru' => 'date',
            'data_lama'    => 'array',
            'data_baru'    => 'array',
            'urutan'       => 'integer',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /** Label isian pada tabel riwayat — kuncinya sama dengan data_lama/data_baru. */
    public const LABEL = [
        'berlaku' => 'Masa berlaku',
        'noregis' => 'Nomor registrasi',
        'nik'     => 'Nomor KTP',
        'mcu'     => 'Masa berlaku MCU',
        'tgl_mcu' => 'Tanggal MCU',
        'cakupan' => 'Cakupan area',
    ];

    /**
     * Keadaan sebuah Mine Permit yang layak dicatat — Riwayat::keadaanPermit.
     *
     * @return array<string,string>
     */
    public static function keadaanPermit(?Permit $p): array
    {
        if (! $p) return [];

        $p->loadMissing(['mcuOrang', 'pekerja']);

        return array_filter([
            'berlaku' => (string) $p->habisEfektif()?->toDateString(),
            'noregis' => (string) $p->no_registrasi,
            'nik'     => (string) ($p->pekerja?->nik ?? $p->mcuOrang?->nik),
            'mcu'     => (string) $p->mcuOrang?->berlaku_sampai?->toDateString(),
            'tgl_mcu' => (string) $p->mcuOrang?->tanggal_periksa?->toDateString(),
            'cakupan' => (string) $p->cakupan_area,
        ], fn ($v) => trim($v) !== '');
    }

    /** Sudah berapa kali dokumen ini diperpanjang. */
    public static function jumlah(string $jenis, int $dokumenId): int
    {
        return static::query()->where('jenis', $jenis)->where('dokumen_id', $dokumenId)->count();
    }

    /**
     * Catat satu perpanjangan yang SUDAH disetujui — Riwayat::catat.
     *
     * DICATAT SAAT DISETUJUI, bukan saat diajukan: pengajuan yang ditolak
     * tidak mengubah masa berlaku apa pun. Persetujuan yang tertekan dua
     * kali tidak menambah hitungan "sudah diperpanjang berapa kali".
     */
    public static function catat(string $jenis, int $dokumenId, array $lama, array $baru, ?int $pengajuanId, ?int $userId): self
    {
        if ($pengajuanId && ($ada = static::query()->where('jenis', $jenis)->where('dokumen_id', $dokumenId)
                ->where('perpanjangan_id', $pengajuanId)->first())) {
            return $ada;
        }

        return static::create([
            'jenis'           => $jenis,
            'dokumen_id'      => $dokumenId,
            'perpanjangan_id' => $pengajuanId,
            'urutan'          => self::jumlah($jenis, $dokumenId) + 1,
            'berlaku_lama'    => $lama['berlaku'] ?? null,
            'berlaku_baru'    => $baru['berlaku'] ?? null,
            'data_lama'       => $lama,
            'data_baru'       => $baru,
            'user_id'         => $userId,
        ]);
    }
}
