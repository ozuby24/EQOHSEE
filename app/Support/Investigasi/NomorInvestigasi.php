<?php

namespace App\Support\Investigasi;

use Illuminate\Support\Facades\DB;

/**
 * Nomor berjalan per awalan dan per tahun — INC-2026-0011, INV-2026-0009.
 *
 * MENGAPA TIDAK MEMAKAI id BARIS. Nomor insiden muncul di berita acara,
 * di laporan ke Inspektur Tambang, dan di papan pengumuman. Memakai id
 * berarti nomornya melompat setiap kali ada baris yang gagal tersimpan,
 * dan lompatan pada dokumen resmi adalah pertanyaan yang harus dijawab
 * seseorang — "mana INC-2026-0007?" tidak punya jawaban yang enak.
 *
 * MENGAPA DIKUNCI. Dua orang yang melapor pada detik yang sama akan
 * membaca nomor terakhir yang sama, lalu keduanya menulis nomor yang
 * sama. `lockForUpdate` di dalam transaksi membuat yang kedua menunggu
 * sampai yang pertama selesai menulis. Tanpa itu, tabrakannya jarang —
 * dan yang jarang justru tidak akan pernah terkejar oleh uji.
 */
final class NomorInvestigasi
{
    public const INSIDEN     = 'INC';
    public const INVESTIGASI = 'INV';
    public const BUKTI       = 'EVD';
    public const TEMUAN      = 'FND';
    public const TINDAKAN    = 'CAR';

    public static function terbitkan(string $awalan, ?int $tahun = null): string
    {
        $tahun ??= (int) now()->format('Y');

        return DB::transaction(function () use ($awalan, $tahun) {
            $baris = DB::table('inv_nomor_urut')
                ->where('prefix', $awalan)->where('tahun', $tahun)
                ->lockForUpdate()->first();

            if ($baris) {
                $urut = $baris->nomor_terakhir + 1;

                DB::table('inv_nomor_urut')->where('id', $baris->id)
                    ->update(['nomor_terakhir' => $urut, 'updated_at' => now()]);
            } else {
                $urut = 1;

                DB::table('inv_nomor_urut')->insert([
                    'prefix' => $awalan, 'tahun' => $tahun, 'nomor_terakhir' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            return sprintf('%s-%d-%04d', $awalan, $tahun, $urut);
        });
    }
}
