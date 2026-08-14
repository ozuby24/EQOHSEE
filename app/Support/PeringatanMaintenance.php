<?php

namespace App\Support;

use App\Models\WorkOrder;
use Illuminate\Support\Collection;

/**
 * Peringatan pemeliharaan dan keandalan.
 *
 * Sebentuk dengan PeringatanOperasi dan PeringatanKonservasi — kode
 * tetap, tingkat, keterangan, saran tindakan — supaya ketiga modul
 * dapat dibaca dengan kebiasaan yang sama.
 *
 * Yang diawasi di sini berbeda sifatnya dari dua modul itu. Produksi
 * dan konservasi menurun perlahan; kerusakan alat datang mendadak,
 * tetapi hampir selalu didahului tanda yang terbaca lebih dulu —
 * konsumsi bahan bakar yang menyimpang, jam menunggu yang memanjang,
 * perawatan berkala yang mulai terlewat. Peringatan di sini menunjuk
 * tanda-tanda itu, bukan kerusakannya yang sudah terjadi.
 */
final class PeringatanMaintenance
{
    public const TINGGI = 'tinggi';
    public const SEDANG = 'sedang';

    /* Ambang. Bernama supaya terlihat saat ditinjau. */
    private const KETERSEDIAAN_MINIMUM = 85.0;  // persen
    private const KEPATUHAN_PM_MINIMUM = 90.0;  // persen
    private const PORSI_MENUNGGU_MAKS  = 40.0;  // persen waktu henti
    private const TUNGGAKAN_MAKS       = 10;    // perintah kerja terbuka

    /** @return list<array{kode:string,level:string,judul:string,ket:string,saran:string}> */
    public static function susun(
        Keandalan $k,
        array $pm,
        array $tunggakan,
        Collection $orders,
        array $biaya,
    ): array {
        $p = [];
        $u = $k->toArray();

        /* ---------- mutu data ---------- */

        $belumDiverifikasi = $orders->filter(fn (WorkOrder $w) => $w->menungguVerifikasi())->count();
        if ($belumDiverifikasi > 0) {
            $p[] = [
                'kode'  => 'wo-belum-diverifikasi',
                'level' => self::SEDANG,
                'judul' => "{$belumDiverifikasi} perintah kerja selesai belum diverifikasi",
                // Ditegaskan bahwa angkanya tetap terhitung. Pada data
                // produksi yang belum disetujui memang dikeluarkan; di
                // sini tidak, sebab mengeluarkan waktu henti justru
                // membuat ketersediaan terlihat lebih bagus.
                'ket'   => 'Jam dan biayanya tetap ikut dihitung, tetapi belum ada yang memeriksanya.',
                'saran' => 'Minta Kepala Teknik Tambang memverifikasi penutupan agar angkanya dapat dipakai pada laporan.',
            ];
        }

        if (($pm['tanpaJadwal'] ?? 0) > 0) {
            $p[] = [
                'kode'  => 'alat-tanpa-jadwal-pm',
                'level' => self::SEDANG,
                'judul' => "{$pm['tanpaJadwal']} alat belum punya jadwal perawatan",
                'ket'   => 'Alat ini tidak ikut menaikkan maupun menurunkan kepatuhan PM — ia tidak terpantau sama sekali.',
                'saran' => 'Tetapkan jenis dan tanggal perawatan berikutnya pada registri Keselamatan Operasi.',
            ];
        }

        if ($u['jamTersedia'] <= 0) {
            $p[] = [
                'kode'  => 'tanpa-armada',
                'level' => self::TINGGI,
                'judul' => 'Belum ada alat terdaftar',
                'ket'   => 'Ketersediaan dan MTBF tidak dapat dihitung tanpa daftar alat.',
                'saran' => 'Daftarkan alat pada modul Keselamatan Operasi; modul ini memakai registri yang sama.',
            ];

            return $p;
        }

        /* ---------- keandalan ---------- */

        if ($u['ketersediaan'] < self::KETERSEDIAAN_MINIMUM) {
            $p[] = [
                'kode'  => 'ketersediaan-rendah',
                'level' => self::TINGGI,
                'judul' => 'Ketersediaan '.number_format($u['ketersediaan'], 1).' %, di bawah '.self::KETERSEDIAAN_MINIMUM.' %',
                'ket'   => number_format($u['jamHenti'], 0).' jam henti dari '.number_format($u['jamTersedia'], 0).' jam tersedia.',
                'saran' => 'Telusuri alat dengan jam henti terbanyak lebih dulu; biasanya sedikit alat menyumbang sebagian besar waktu henti.',
            ];
        }

        // Inilah peringatan yang paling sering menunjuk ke tempat yang
        // benar, dan yang paling jarang terlihat karena kedua angkanya
        // biasa digabung menjadi satu MTTR.
        if ($u['jamHenti'] > 0 && $u['porsiMenunggu'] > self::PORSI_MENUNGGU_MAKS) {
            $p[] = [
                'kode'  => 'menunggu-terlalu-lama',
                'level' => self::TINGGI,
                'judul' => number_format($u['porsiMenunggu'], 1).' % waktu henti habis menunggu',
                'ket'   => number_format($u['jamMenunggu'], 0).' jam alat berhenti tanpa ada yang mengerjakannya.',
                'saran' => 'Periksa ketersediaan suku cadang dan penjadwalan montir. Menambah kapasitas bengkel tidak memperbaiki waktu tunggu.',
            ];
        }

        /* ---------- perawatan berkala ---------- */

        if (($pm['berjadwal'] ?? 0) > 0 && $pm['persen'] < self::KEPATUHAN_PM_MINIMUM) {
            $p[] = [
                'kode'  => 'kepatuhan-pm-rendah',
                'level' => self::TINGGI,
                'judul' => "Kepatuhan PM {$pm['persen']} %, {$pm['terlambat']} alat terlewat jadwal",
                'ket'   => 'Perawatan yang terlewat berujung pada kerusakan yang lebih mahal daripada perawatannya.',
                'saran' => 'Jadwalkan ulang PM yang terlewat pada alat berkritikalitas tertinggi lebih dahulu.',
            ];
        }

        /* ---------- tunggakan ---------- */

        if (($tunggakan['kritis'] ?? 0) > 0) {
            $p[] = [
                'kode'  => 'tunggakan-kritis',
                'level' => self::TINGGI,
                'judul' => "{$tunggakan['kritis']} perintah kerja prioritas kritis masih terbuka",
                'ket'   => 'Tertahan terlama '.number_format($tunggakan['terlamaJam'] ?? 0, 0).' jam.',
                'saran' => 'Selesaikan atau turunkan prioritasnya dengan alasan yang tercatat; tunggakan kritis yang menetap berarti prioritasnya tidak lagi berarti.',
            ];
        }

        if (($tunggakan['jumlah'] ?? 0) > self::TUNGGAKAN_MAKS) {
            $p[] = [
                'kode'  => 'tunggakan-menumpuk',
                'level' => self::SEDANG,
                'judul' => "{$tunggakan['jumlah']} perintah kerja terbuka",
                'ket'   => ($tunggakan['belumMulai'] ?? 0).' di antaranya belum mulai dikerjakan sama sekali.',
                'saran' => 'Tinjau kapasitas bengkel terhadap laju gangguan; tunggakan yang terus tumbuh tidak akan terkejar oleh lembur.',
            ];
        }

        /* ---------- biaya ---------- */

        if (($biaya['tonDasar'] ?? 0) <= 0 && ($biaya['total'] ?? 0) > 0) {
            $p[] = [
                'kode'  => 'biaya-tanpa-produksi',
                'level' => self::SEDANG,
                'judul' => 'Biaya pemeliharaan tercatat tanpa produksi pembanding',
                'ket'   => 'Belum ada catatan produksi yang disetujui pada periode ini, sehingga biaya per ton tidak dapat dihitung.',
                'saran' => 'Ajukan dan setujui laporan shift pada modul Operasi agar biaya per ton bermakna.',
            ];
        }

        return $p;
    }
}
