<?php

namespace App\Support\Miners;

use App\Models\Miners\{Mcu, McuOrang, Permit};
use App\Support\Waktu;

/**
 * Sinkron masa berlaku — `masa-berlaku:sync` dan `app:update-mcu-status`
 * Safe Track, dijalankan setiap pagi oleh `miners:sinkron`.
 *
 *   1. Mine Permit yang MENGIKUTI MCU disamakan dengan masa berlaku MCU
 *      dasarnya. Kunci manual dan tanggal perpanjangan tidak disentuh;
 *      kartu yang sudah dinonaktifkan dibiarkan membeku pada tanggal
 *      terakhirnya.
 *   2. Mine Permit terbit yang tanggalnya lewat ditandai `kedaluwarsa`
 *      (status 4 Safe Track); yang sudah diperpanjang — tanggalnya
 *      kembali ke depan — dikembalikan ke `terbit`.
 *   3. Surat MCU yang sudah disetujui dan memuat nama yang masa
 *      berlakunya lewat ditandai `kedaluwarsa`, dan sebaliknya.
 *
 * BARIS DEMI BARIS lewat Eloquent, tanpa UPDATE … JOIN: volumenya ribuan,
 * dan perintahnya harus berjalan sama di SQLite, MySQL, dan PostgreSQL.
 *
 * Satu penyimpangan dari Safe Track, disengaja: permit yang MASIH DALAM
 * PERSETUJUAN tidak ditandai expired. Safe Track menandai status 1 dan 2;
 * permit open yang ditandai 4 lenyap dari antrean penyetujunya dan tidak
 * dapat ditindak siapa pun.
 */
final class SinkronMasaBerlaku
{
    /** @return array{permit:int,expired:int,pulih:int,mcu:int,mcuPulih:int} */
    public static function jalankan(): array
    {
        return [
            'permit' => self::permitDariMcu(),
            ...self::tandaiPermit(),
            ...self::tandaiMcu(),
        ];
    }

    /** Aturan 1. @return int jumlah permit yang tanggalnya berubah */
    public static function permitDariMcu(): int
    {
        $n = 0;

        Permit::withoutGlobalScopes()->with('mcuOrang')
            ->where('aktif', true)
            ->where(fn ($q) => $q->whereNull('sumber_berlaku')->orWhere('sumber_berlaku', 'mcu'))
            ->whereNotNull('mcu_orang_id')
            ->chunkById(500, function ($permit) use (&$n) {
                foreach ($permit as $p) {
                    $mcu = $p->mcuOrang?->berlaku_sampai;

                    if (! $mcu || $p->berlaku_sampai?->toDateString() === $mcu->toDateString()) continue;

                    $p->forceFill(['berlaku_sampai' => $mcu, 'sumber_berlaku' => 'mcu'])->save();
                    $n++;
                }
            });

        return $n;
    }

    /** Aturan 2. @return array{expired:int,pulih:int} */
    public static function tandaiPermit(): array
    {
        $hasil = ['expired' => 0, 'pulih' => 0];

        Permit::withoutGlobalScopes()->with('mcuOrang')
            ->where('aktif', true)->whereIn('status', Permit::TERBIT)
            ->chunkById(500, function ($permit) use (&$hasil) {
                foreach ($permit as $p) {
                    $habis = $p->habisEfektif();

                    $sisa = Waktu::sisaHari($habis);

                    if ($p->status === 'terbit' && $sisa !== null && $sisa < 0) {
                        $p->forceFill(['status' => 'kedaluwarsa'])->save();
                        $hasil['expired']++;
                    } elseif ($p->status === 'kedaluwarsa' && $sisa !== null && $sisa >= 0) {
                        $p->forceFill(['status' => 'terbit'])->save();
                        $hasil['pulih']++;
                    }
                }
            });

        return $hasil;
    }

    /** Aturan 3. @return array{mcu:int,mcuPulih:int} */
    public static function tandaiMcu(): array
    {
        $hari = Waktu::kini()->toDateString();

        $lewat = McuOrang::withoutGlobalScopes()->whereNotNull('berlaku_sampai')
            ->whereDate('berlaku_sampai', '<', $hari)->select('mcu_id');

        $mcu = Mcu::withoutGlobalScopes()->where('status', 'selesai')->whereIn('id', $lewat)
            ->update(['status' => 'kedaluwarsa']);

        $pulih = Mcu::withoutGlobalScopes()->where('status', 'kedaluwarsa')->whereNotIn('id', $lewat)
            ->update(['status' => 'selesai']);

        return ['mcu' => $mcu, 'mcuPulih' => $pulih];
    }
}
