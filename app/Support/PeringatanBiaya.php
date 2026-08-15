<?php

namespace App\Support;

/**
 * Peringatan pengendalian biaya.
 *
 * Seluruhnya dihitung dari realisasi yang sudah disetujui, tanpa
 * kecuali — dan di modul inilah pengecualian paling berbahaya. Biaya
 * yang belum tercatat membuat setiap indikator terlihat membaik
 * sekaligus: serapan turun, biaya per ton turun, selisih tarif berbalik
 * menghemat. Tidak ada satu pun angka yang tampak ganjil, sebab semuanya
 * bergerak ke arah yang sama dan ke arah yang menyenangkan.
 *
 * Karena itu peringatan yang paling penting di sini justru bukan tentang
 * pemborosan melainkan tentang kelengkapan: akun tanpa anggaran, bulan
 * tanpa realisasi, produksi yang belum disetujui. Ketiganya menjawab
 * pertanyaan "boleh dipercaya sejauh mana angka di halaman ini".
 */
final class PeringatanBiaya
{
    public const TINGGI = 'tinggi';
    public const SEDANG = 'sedang';

    /**
     * @param array<string,mixed> $rekap keluaran BiayaController::rekap()
     * @return list<array{kode:string,level:string,judul:string,ket:string,saran:string}>
     */
    public static function susun(array $rekap): array
    {
        $p = [];

        foreach (self::kelengkapan($rekap) as $q) $p[] = $q;
        foreach (self::laju($rekap) as $q) $p[] = $q;
        foreach (self::tarif($rekap) as $q) $p[] = $q;

        return $p;
    }

    private static function kelengkapan(array $r): array
    {
        $p = [];
        $tahun = $r['tahun'];

        if (($r['total']['anggaran'] ?? 0) <= 0) {
            $p[] = [
                'kode'  => 'anggaran-kosong',
                'level' => self::TINGGI,
                'judul' => "Anggaran {$tahun} belum disusun",
                'ket'   => 'Tanpa anggaran, realisasi hanya dapat dijumlahkan dan tidak dapat dinilai — '
                           .'tidak ada pembanding bagi satu pun angka di halaman ini.',
                'saran' => 'Salin pagu tiap akun dari RKAB yang disahkan beserta kuantitas rencananya.',
            ];
        }

        if (!($r['produksi']['adaData'] ?? false)) {
            $p[] = [
                'kode'  => 'produksi-belum-disetujui',
                'level' => self::TINGGI,
                'judul' => 'Belum ada catatan produksi yang disetujui pada '.$tahun,
                'ket'   => 'Biaya per ton dan pemecahan selisih memakai tonase dari Mine Operations. '
                           .'Selama catatannya belum ditinjau, seluruh angka satuan di halaman ini kosong — '
                           .'bukan nol, melainkan tidak diketahui.',
                'saran' => 'Tinjau catatan shift di Mine Operations; biaya di sini tidak mencatat ulang tonase '
                           .'agar tidak ada dua angka produksi yang berselisih.',
            ];
        }

        $tanpa = $r['tanpaAnggaran'] ?? [];
        if ($tanpa !== []) {
            $p[] = [
                'kode'  => 'akun-tanpa-anggaran',
                'level' => self::SEDANG,
                'judul' => count($tanpa).' akun berbiaya tanpa pagu anggaran',
                'ket'   => 'Realisasinya ikut menambah total, tetapi selisihnya tidak dapat dihitung — '
                           .'akun seperti ini tidak pernah tampak melampaui apa pun.',
                'saran' => 'Lengkapi pagu untuk: '.implode(', ', array_slice($tanpa, 0, 6))
                           .(count($tanpa) > 6 ? ', dan lainnya.' : '.'),
            ];
        }

        $menunggu = (int) ($r['menunggu'] ?? 0);
        if ($menunggu > 0) {
            $p[] = [
                'kode'  => 'menunggu-tinjauan',
                'level' => self::SEDANG,
                'judul' => $menunggu.' baris realisasi menunggu tinjauan',
                'ket'   => 'Selama belum ditinjau, angkanya tidak ikut dihitung — sehingga seluruh indikator '
                           .'di halaman ini sedang menunjukkan biaya yang lebih rendah daripada yang sebenarnya.',
                'saran' => 'Tinjau realisasi bulan berjalan sebelum angka ini dipakai rapat.',
            ];
        }

        return $p;
    }

    private static function laju(array $r): array
    {
        $p = [];
        $baca = $r['bacaSerapan'] ?? ['kelas' => 'tak-diketahui'];

        if ($baca['kelas'] === 'mendahului') {
            $p[] = [
                'kode'  => 'serapan-mendahului',
                'level' => self::TINGGI,
                'judul' => 'Serapan anggaran mendahului kemajuan produksi',
                'ket'   => $baca['ket'],
                'saran' => 'Telusuri akun dengan selisih tarif terbesar di bawah; itulah bagian yang tidak dijelaskan '
                           .'oleh banyaknya material yang dipindahkan.',
            ];
        }

        if ($baca['kelas'] === 'tertinggal') {
            $p[] = [
                'kode'  => 'serapan-tertinggal',
                'level' => self::SEDANG,
                'judul' => 'Serapan anggaran jauh tertinggal dari produksi',
                'ket'   => $baca['ket'],
                'saran' => 'Periksa akun yang belum ada realisasinya bulan ini sebelum menyimpulkan penghematan.',
            ];
        }

        $proyeksi = $r['total']['proyeksi'] ?? null;
        $anggaran = (float) ($r['total']['anggaran'] ?? 0);

        if ($proyeksi !== null && $anggaran > 0 && $proyeksi > $anggaran) {
            $lebih = $proyeksi - $anggaran;
            $p[] = [
                'kode'  => 'proyeksi-melampaui',
                'level' => self::TINGGI,
                'judul' => 'Proyeksi akhir tahun melampaui pagu '
                           .number_format($lebih / max($anggaran, 1) * 100, 1).'%',
                'ket'   => 'Pada laju '.($r['bulanTerisi'] ?? 0).' bulan yang sudah lengkap, realisasi akhir tahun '
                           .'diperkirakan Rp '.number_format($proyeksi, 0, ',', '.')
                           .' terhadap pagu Rp '.number_format($anggaran, 0, ',', '.')
                           .'. Proyeksi memakai laju rata-rata, jadi ia tidak memperhitungkan pekerjaan besar '
                           .'yang sudah dijadwalkan maupun yang sudah selesai.',
                'saran' => 'Ajukan revisi RKAB atau tahan pekerjaan yang belum mengikat, mana yang lebih dulu diperlukan.',
            ];
        }

        $selisihSr = $r['produksi']['selisihSr'] ?? null;
        if ($selisihSr !== null && abs($selisihSr) > Biaya::AMBANG_SELISIH_NISBAH_PERSEN) {
            $p[] = [
                'kode'  => 'nisbah-bergeser',
                'level' => self::SEDANG,
                'judul' => 'Nisbah kupas bergeser '.number_format($selisihSr, 1).'% dari rencana',
                'ket'   => 'Biaya per ton batubara bergerak dengan sendirinya ketika nisbah kupas bergerak, '
                           .'tanpa satu pun perbaikan maupun pemborosan di lapangan. Pada selisih sebesar ini, '
                           .'biaya per ton tahun ini dan tahun lalu tidak sebanding.',
                'saran' => 'Pakai biaya per BCM untuk menilai kinerja, dan sebutkan nisbahnya setiap kali '
                           .'biaya per ton dilaporkan.',
            ];
        }

        return $p;
    }

    private static function tarif(array $r): array
    {
        $p = [];

        foreach ($r['akun'] ?? [] as $a) {
            if (!($a['tarifMenonjol'] ?? false)) continue;

            $tarif = (float) $a['varians']['tarif'];
            $arah = $tarif > 0 ? 'melampaui' : 'di bawah';

            // Bila akunnya bersatuan, sebutkan bagian mana yang dapat
            // dikendalikan dari pit. Harga solar naik bukan karena ada
            // yang boros di muka gali.
            $rinci = '';
            if (($a['hargaPakai']['dapatDipecah'] ?? false)) {
                $rinci = ' Selisih tarif itu sendiri terbagi dua: Rp '
                         .number_format(abs((float) $a['hargaPakai']['harga']), 0, ',', '.')
                         .' dari harga satuan dan Rp '.number_format(abs((float) $a['hargaPakai']['pakai']), 0, ',', '.')
                         .' dari pemakaian; hanya yang kedua dapat dikendalikan dari pit.';
            }

            $p[] = [
                'kode'  => 'tarif-'.$a['akunId'],
                'level' => self::SEDANG,
                'judul' => $a['akun'].' — biaya satuan '.$arah.' anggaran Rp '
                           .number_format(abs($tarif), 0, ',', '.'),
                'ket'   => 'Selisih ini sudah dibersihkan dari pengaruh volume: ia tetap ada seandainya '
                           .'material yang dipindahkan persis sebanyak rencana.'.$rinci,
                'saran' => $a['satuan']
                    ? 'Bandingkan pemakaian per satuan produksi dengan bulan-bulan sebelumnya sebelum menyimpulkan sebabnya.'
                    : 'Telusuri rincian akun ini pada bulan dengan realisasi terbesar.',
            ];
        }

        return $p;
    }
}
