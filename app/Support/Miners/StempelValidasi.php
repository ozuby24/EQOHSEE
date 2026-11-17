<?php

namespace App\Support\Miners;

use App\Models\Company;
use App\Models\Miners\McuOrang;
use App\Support\Waktu;

/**
 * Stempel validasi MCU — App\Services\Mcu\StempelValidasi Safe Track.
 *
 * Satu tempat yang memutuskan "apakah hasil MCU ini masih sah", dipakai
 * bersama oleh sertifikat cetak dan layar rincian manpower, supaya
 * stempel di kertas dan keterangan di layar tidak pernah mengatakan dua
 * hal yang berbeda tentang orang yang sama.
 *
 * STEMPELNYA DIHITUNG, BUKAN GAMBAR. Gambar stempel ikut tercetak pada
 * MCU yang sudah habis masa berlakunya dan pada orang yang dinyatakan
 * Unfit, sebab gambar tidak tahu apa pun tentang barisnya. Yang di sini
 * hanya berbunyi TERVALIDASI bila ketiganya benar:
 *
 *   1. surat MCU-nya sudah disetujui dokter (status selesai/kedaluwarsa),
 *   2. hasilnya menyatakan layak kerja (master `layak`),
 *   3. masa berlakunya belum lewat.
 *
 * Bila salah satunya tidak terpenuhi, yang tercetak adalah keadaan yang
 * sebenarnya — KEDALUWARSA, TIDAK LAYAK KERJA, atau BELUM TERVALIDASI —
 * beserta alasannya.
 */
final class StempelValidasi
{
    public const SAH         = 'sah';
    public const KEDALUWARSA = 'kedaluwarsa';
    public const TIDAK_LAYAK = 'tidak_layak';
    public const BELUM       = 'belum';

    /** Status surat MCU yang berarti sudah disetujui dokter. */
    public const DISETUJUI = ['selesai', 'kedaluwarsa'];

    private const RUPA = [
        self::SAH         => ['judul' => 'TERVALIDASI',       'hex' => '#166534', 'nada' => 'sukses'],
        self::KEDALUWARSA => ['judul' => 'KEDALUWARSA',       'hex' => '#b91c1c', 'nada' => 'bahaya'],
        self::TIDAK_LAYAK => ['judul' => 'TIDAK LAYAK KERJA', 'hex' => '#b91c1c', 'nada' => 'bahaya'],
        self::BELUM       => ['judul' => 'BELUM TERVALIDASI', 'hex' => '#6b7280', 'nada' => 'abu'],
    ];

    /**
     * Keterangan stempel untuk satu manpower.
     *
     * @return array<string,mixed>
     */
    public static function untuk(?McuOrang $o, ?Company $iup = null): array
    {
        if ($o === null) {
            return self::bungkus(self::BELUM, 'Data MCU tidak ditemukan.', null, $iup);
        }

        $o->loadMissing(['mcu', 'hasil']);

        $iup ??= $o->mcu?->iup_id ? Company::query()->find($o->mcu->iup_id) : null;

        $keadaan = null;
        $alasan  = '';

        if (! in_array($o->mcu?->status, self::DISETUJUI, true)) {
            [$keadaan, $alasan] = [self::BELUM, 'Pengajuan MCU-nya belum disetujui dokter.'];
        } elseif ($o->belumDinilai()) {
            [$keadaan, $alasan] = [self::BELUM, 'Hasil MCU belum ditetapkan dokter.'];
        } elseif (! $o->layak()) {
            [$keadaan, $alasan] = [self::TIDAK_LAYAK, 'Hasil MCU "'.$o->hasil?->nama.'" tidak menyatakan layak kerja.'];
        }

        /* Masa berlaku diperiksa hanya setelah hasilnya meloloskan; bila
           orangnya memang Unfit, tanggal kedaluwarsa bukan lagi soalnya. */
        if ($keadaan === null) {
            $sisa = $o->sisaHari();

            if ($o->berlaku_sampai === null) {
                [$keadaan, $alasan] = [self::BELUM, 'Masa berlaku MCU (Date of next MCU) belum diisi.'];
            } elseif ($sisa < 0) {
                [$keadaan, $alasan] = [self::KEDALUWARSA, 'Masa berlakunya lewat '.abs($sisa).' hari pada '
                    .$o->berlaku_sampai->format('d-m-Y').'.'];
            } else {
                [$keadaan, $alasan] = [self::SAH, 'Berlaku sampai '.$o->berlaku_sampai->format('d-m-Y')
                    .' ('.$sisa.' hari lagi).'];
            }
        }

        return self::bungkus($keadaan, $alasan, $o, $iup);
    }

    /** @return array<string,mixed> */
    private static function bungkus(string $keadaan, string $alasan, ?McuOrang $o, ?Company $iup): array
    {
        return self::RUPA[$keadaan] + [
            'keadaan'  => $keadaan,
            'sah'      => $keadaan === self::SAH,
            'alasan'   => $alasan,
            'nomor'    => $o ? 'MCU-'.$o->id : '',
            'nama'     => $o?->nama ?? '',
            'hasil'    => $o?->hasil?->nama ?? 'Belum dinilai',
            'tglMcu'   => $o?->tanggal_periksa?->format('d-m-Y') ?? '',
            'berlaku'  => $o?->berlaku_sampai?->format('d-m-Y') ?? '',
            'penerbit' => $iup?->name ?? '',
            'dokter'   => self::namaDokter($o, $iup?->id),
            'dicetak'  => Waktu::kini()->format('d-m-Y H:i'),
        ];
    }

    /**
     * Nama dokter pemeriksa — tanda tangan sertifikat.
     *
     * Yang DITUGASI pada langkah dokter surat MCU-nya lebih dulu, bukan
     * dokter skema saat ini: sertifikat tahun lalu tidak boleh berganti
     * penanda tangan hanya karena dokter IUP-nya berganti. Skema IUP
     * menjadi cadangan bagi surat lama tanpa langkah dokter.
     */
    public static function namaDokter(?McuOrang $o, ?int $iupId): ?string
    {
        $id = $o?->mcu ? \App\Models\Miners\Alur::query()->where('dokumen', 'mcu')
            ->where('dokumen_id', $o->mcu_id)->where('peran', 'dokter')->value('ditugaskan_user_id') : null;

        $id ??= Penyetuju::id($iupId, 'dokter');

        return $id ? \App\Models\User::query()->whereKey($id)->value('name') : null;
    }
}
