<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Peringatan konservasi mineral dan batubara.
 *
 * Sejajar dengan PeringatanOperasi dan sengaja memakai bentuk yang sama —
 * kode tetap, tingkat, keterangan, dan saran tindakan — supaya kedua
 * modul dapat dibaca dengan kebiasaan yang sama, dan supaya tampilan
 * peringatannya kelak dapat dipakai bersama tanpa disesuaikan dua kali.
 *
 * Yang diawasi di sini adalah kaidah konservasi: seberapa banyak mineral
 * yang benar-benar terambil dari yang tergali, seberapa banyak yang
 * hilang, dan seberapa besar pengotoran. Ketiganya menurun perlahan dan
 * jarang menimbulkan keluhan pada harinya — yang terlihat hanya cadangan
 * yang habis lebih cepat daripada rencana, beberapa tahun kemudian.
 */
final class PeringatanKonservasi
{
    public const TINGGI = 'tinggi';
    public const SEDANG = 'sedang';

    /* Ambang. Dinyatakan bernama supaya terlihat saat ditinjau, dan
       supaya perubahannya menjadi keputusan yang disengaja. */
    private const RECOVERY_MINIMUM   = 85.0;  // persen
    private const KEHILANGAN_MAKSIMUM = 5.0;  // persen terhadap material digali
    private const DILUSI_MAKSIMUM     = 10.0; // persen terhadap produksi aktual
    private const CAPAIAN_MINIMUM     = 90.0; // persen terhadap target

    /** @return list<array{kode:string,level:string,judul:string,ket:string,saran:string}> */
    public static function susun(
        array $ringkas,
        Collection $records,
        Collection $actions,
    ): array {
        $p = [];

        /* ---------- mutu data ---------- */

        $menunggu = $records->where('status', Alur::DIAJUKAN)->count();
        if ($menunggu > 0) {
            $p[] = [
                'kode'  => 'menunggu-tinjauan',
                'level' => self::SEDANG,
                'judul' => "{$menunggu} data menunggu tinjauan",
                'ket'   => 'Sudah diajukan tetapi belum disetujui, sehingga belum terhitung.',
                'saran' => 'Minta Kepala Teknik Tambang meninjau data yang tertahan.',
            ];
        }

        $draf = $records->whereIn('status', [Alur::DRAF, Alur::DITOLAK])->count();
        if ($draf > 0) {
            $p[] = [
                'kode'  => 'masih-draf',
                'level' => self::SEDANG,
                'judul' => "{$draf} data belum diajukan",
                'ket'   => 'Masih berstatus draf atau ditolak, belum masuk hitungan.',
                'saran' => 'Lengkapi lalu ajukan agar angkanya terhitung pada laporan periode ini.',
            ];
        }

        if (($ringkas['jumlah_record'] ?? 0) === 0) {
            $p[] = [
                'kode'  => 'tanpa-data',
                'level' => self::TINGGI,
                'judul' => 'Belum ada data terhitung',
                'ket'   => 'Belum ada catatan konservasi yang disetujui pada tahun ini.',
                'saran' => 'Masukkan produksi, material tergali, kehilangan, dan dilusi, lalu ajukan untuk ditinjau.',
            ];

            return $p;
        }

        /* ---------- kaidah konservasi ---------- */

        $recovery = (float) ($ringkas['recovery'] ?? 0);
        if ($recovery > 0 && $recovery < self::RECOVERY_MINIMUM) {
            $p[] = [
                'kode'  => 'recovery-rendah',
                'level' => self::TINGGI,
                'judul' => 'Recovery '.number_format($recovery, 1).' %, di bawah '.self::RECOVERY_MINIMUM.' %',
                'ket'   => 'Sebagian mineral yang sudah tergali tidak terambil.',
                'saran' => 'Periksa batas penambangan, kendali kadar, dan kehilangan pada pemuatan serta pengolahan.',
            ];
        }

        $digali = (float) ($ringkas['material_digali'] ?? 0);
        $hilang = (float) ($ringkas['kehilangan_material'] ?? 0);
        if ($digali > 0) {
            $porsi = $hilang / $digali * 100;

            if ($porsi > self::KEHILANGAN_MAKSIMUM) {
                $p[] = [
                    'kode'  => 'kehilangan-tinggi',
                    'level' => self::TINGGI,
                    'judul' => 'Kehilangan material '.number_format($porsi, 1).' % dari yang tergali',
                    'ket'   => number_format($hilang, 0).' dari '.number_format($digali, 0).' material tergali.',
                    'saran' => 'Telusuri titik kehilangan: pemuatan, pengangkutan, penimbunan sementara, atau pengolahan.',
                ];
            }
        }

        $aktual = (float) ($ringkas['produksi_aktual'] ?? 0);
        $dilusi = (float) ($ringkas['dilusi'] ?? 0);
        if ($aktual > 0) {
            $porsi = $dilusi / $aktual * 100;

            if ($porsi > self::DILUSI_MAKSIMUM) {
                $p[] = [
                    'kode'  => 'dilusi-tinggi',
                    'level' => self::SEDANG,
                    'judul' => 'Dilusi '.number_format($porsi, 1).' % terhadap produksi',
                    'ket'   => 'Pengotoran menurunkan kadar dan menaikkan biaya pengolahan.',
                    'saran' => 'Tinjau ketelitian penggalian pada batas lapisan dan kendali operator alat gali.',
                ];
            }
        }

        if (($ringkas['target_produksi'] ?? 0) > 0 && ($ringkas['capaian_target'] ?? 0) < self::CAPAIAN_MINIMUM) {
            $p[] = [
                'kode'  => 'capaian-produksi',
                'level' => self::SEDANG,
                'judul' => 'Capaian produksi '.number_format($ringkas['capaian_target'], 1).' % dari target',
                'ket'   => 'Realisasi tertinggal dari rencana tahunan.',
                'saran' => 'Bandingkan per komoditas; cari yang paling tertinggal sebelum menambah kapasitas.',
            ];
        }

        /* ---------- tindak lanjut ---------- */

        // Dihitung dari tanggal, bukan dari status yang tersimpan.
        // Status 'terlambat' yang disimpan tidak pernah bertambah sendiri:
        // baris bertanda "berjalan" yang targetnya lewat sebulan lalu tetap
        // terbaca berjalan sampai ada yang menyuntingnya, dan yang luput
        // justru yang paling perlu ditagih.
        $terlambat = $actions->filter(fn ($a) => $a->terlambat())->count();
        if ($terlambat > 0) {
            $p[] = [
                'kode'  => 'tindak-lanjut-terlambat',
                'level' => self::TINGGI,
                'judul' => "{$terlambat} tindak lanjut terlambat",
                'ket'   => 'Sudah melewati target selesai dan belum ditutup.',
                'saran' => 'Tetapkan ulang target selesai bersama penanggung jawabnya, atau tutup bila sudah tidak berlaku.',
            ];
        }

        return $p;
    }
}
