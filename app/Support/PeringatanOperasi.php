<?php

namespace App\Support;

/**
 * Peringatan ruang kendali operasi.
 *
 * Dipisahkan dari controller karena tiga alasan yang semuanya sudah
 * mulai terasa: ambangnya tadinya berupa angka telanjang di tengah
 * rangkaian `if` (0.90, 1.15, .15) sehingga tidak ada yang tahu dari
 * mana asalnya; aturannya tidak dapat diuji tanpa menembak seluruh
 * halaman; dan tiap peringatan hanya menyebut apa yang salah tanpa
 * menyebut apa yang harus dikerjakan.
 *
 * Setiap peringatan membawa `kode` yang tetap. Kode itu yang kelak
 * menautkan peringatan ke tindak lanjutnya — tanpa penanda yang stabil,
 * satu-satunya penaut yang tersedia adalah judulnya, dan judul berubah
 * setiap kali kalimatnya diperbaiki.
 *
 * Urutannya dari yang paling mendesak. Peringatan tentang kelengkapan
 * data sengaja diletakkan paling atas: selama laporannya belum lengkap,
 * seluruh angka di bawahnya belum tentu berarti apa-apa, dan menindak
 * capaian rendah yang sebenarnya hanya laporan yang belum masuk adalah
 * salah alamat.
 */
final class PeringatanOperasi
{
    public const TINGGI = 'tinggi';
    public const SEDANG = 'sedang';
    public const RENDAH = 'rendah';

    /* Ambang. Dinyatakan sebagai konstanta bernama supaya terlihat saat
       ditinjau, dan supaya perubahannya menjadi keputusan yang disengaja. */
    private const KELENGKAPAN_MINIMUM   = 90.0;  // persen shift wajib yang sudah disetujui
    private const CAPAIAN_MINIMUM       = 90.0;  // persen terhadap target periode
    private const TOLERANSI_STRIP_RATIO = 1.15;  // 15 % di atas rencana
    private const TOLERANSI_JARAK       = 1.15;
    private const DELAY_MAKSIMUM        = 0.15;  // bagian waktu tercatat sebagai delay
    private const KENAIKAN_FUEL         = 10.0;  // persen terhadap periode sebelumnya

    /** @return list<array{kode:string,level:string,judul:string,ket:string,saran:string}> */
    public static function susun(
        array $ringkas,
        array $target,
        Ramalan $ramalan,
        KelengkapanShift $kelengkapan,
        ?float $fuelPerTonBeda = null,
    ): array {
        $p = [];

        /* ---------- mutu data ---------- */

        if ($kelengkapan->belumDilaporkan() > 0) {
            $n = $kelengkapan->belumDilaporkan();
            $p[] = [
                'kode'  => 'shift-belum-dilaporkan',
                'level' => $n > 2 ? self::TINGGI : self::SEDANG,
                'judul' => "{$n} shift belum dilaporkan",
                'ket'   => 'Angka pada halaman ini belum mewakili seluruh periode.',
                'saran' => 'Tagih laporan shift kepada pengawas lapangan sebelum capaian dinilai.',
            ];
        }

        if ($kelengkapan->menungguTinjauan() > 0) {
            $n = $kelengkapan->menungguTinjauan();
            $p[] = [
                'kode'  => 'shift-menunggu-tinjauan',
                'level' => self::SEDANG,
                'judul' => "{$n} shift menunggu tinjauan",
                'ket'   => 'Sudah dilaporkan tetapi belum disetujui, sehingga belum terhitung.',
                'saran' => 'Minta Kepala Teknik Tambang meninjau data yang tertahan.',
            ];
        }

        if ($ringkas['jumlah_record'] === 0) {
            $p[] = [
                'kode'  => 'tanpa-data',
                'level' => self::TINGGI,
                'judul' => 'Belum ada data terhitung',
                'ket'   => 'Belum ada laporan shift yang disetujui pada periode ini.',
                'saran' => 'Masukkan produksi, OB, jarak, dan jam delay dari laporan shift, lalu ajukan untuk ditinjau.',
            ];

            return $p;   // sisanya tidak berarti tanpa data
        }

        /* ---------- ramalan ---------- */

        if (in_array($ramalan->status(), ['meleset', 'berisiko'], true)) {
            $pengali = $ramalan->pengaliDibutuhkan();
            $p[] = [
                'kode'  => 'ramalan-meleset',
                'level' => $ramalan->status() === 'meleset' ? self::TINGGI : self::SEDANG,
                'judul' => 'Proyeksi akhir periode '.number_format($ramalan->proyeksiPersen(), 1).' % dari target',
                'ket'   => 'Kekurangan yang diperkirakan '.number_format($ramalan->kekurangan(), 0)
                           .' pada '.$ramalan->hariTersisa().' hari tersisa.',
                'saran' => $pengali > 0
                    ? 'Laju sisa periode perlu '.number_format($pengali, 2).'× rata-rata sejauh ini.'
                    : 'Periode sudah berakhir; tetapkan pemulihan pada periode berikutnya.',
            ];
        }

        /* ---------- capaian ---------- */

        if (($target['produksi'] ?? 0) > 0 && $ringkas['capaian_produksi'] < self::CAPAIAN_MINIMUM) {
            $p[] = [
                'kode'  => 'capaian-produksi',
                'level' => self::TINGGI,
                'judul' => 'Capaian produksi di bawah '.self::CAPAIAN_MINIMUM.' %',
                'ket'   => number_format($ringkas['capaian_produksi'], 1).' % terhadap target periode.',
                'saran' => 'Telusuri per pit: cari yang capaiannya paling tertinggal, periksa delay dan jarak angkutnya.',
            ];
        }

        if (($target['ob'] ?? 0) > 0 && $ringkas['capaian_ob'] < self::CAPAIAN_MINIMUM) {
            $p[] = [
                'kode'  => 'capaian-ob',
                'level' => self::SEDANG,
                'judul' => 'Capaian pemindahan OB rendah',
                'ket'   => number_format($ringkas['capaian_ob'], 1).' % terhadap target periode.',
                'saran' => 'OB yang tertinggal menumpuk menjadi beban bulan berikutnya; jadwalkan tambahan alat gali.',
            ];
        }

        /* ---------- efisiensi ---------- */

        $ts = $target['strip_ratio'] ?? null;
        if ($ts && $ringkas['strip_ratio'] > $ts * self::TOLERANSI_STRIP_RATIO) {
            $p[] = [
                'kode'  => 'strip-ratio',
                'level' => self::TINGGI,
                'judul' => 'Strip ratio melewati rencana',
                'ket'   => number_format($ringkas['strip_ratio'], 2).' vs rencana '.number_format($ts, 2).'.',
                'saran' => 'Periksa kesesuaian desain pit dan sekuens penambangan terhadap realisasi.',
            ];
        }

        $tj = $target['jarak'] ?? null;
        if ($tj && $ringkas['jarak_rata'] > $tj * self::TOLERANSI_JARAK) {
            $p[] = [
                'kode'  => 'jarak-angkut',
                'level' => self::SEDANG,
                'judul' => 'Jarak angkut meningkat',
                'ket'   => number_format($ringkas['jarak_rata'], 2).' km vs rencana '.number_format($tj, 2).' km.',
                'saran' => 'Tinjau posisi disposal dan ROM; jarak yang memanjang menaikkan biaya dan konsumsi bahan bakar.',
            ];
        }

        $porsiDelay = 100 - $ringkas['efisiensi_waktu'];
        if ($ringkas['delay_jam'] > 0 && $porsiDelay > self::DELAY_MAKSIMUM * 100) {
            $p[] = [
                'kode'  => 'delay-tinggi',
                'level' => self::SEDANG,
                'judul' => 'Delay operasi tinggi',
                'ket'   => number_format($porsiDelay, 1).' % waktu tercatat sebagai delay.',
                'saran' => 'Uraikan penyebab delay per pit; hujan, antrean, dan kerusakan menuntut tindakan yang berbeda.',
            ];
        }

        /* ---------- tautan ke energi ---------- */

        if ($fuelPerTonBeda !== null && $fuelPerTonBeda > self::KENAIKAN_FUEL) {
            $p[] = [
                'kode'  => 'fuel-per-ton',
                'level' => self::SEDANG,
                'judul' => 'Bahan bakar per ton naik '.number_format($fuelPerTonBeda, 1).' %',
                'ket'   => 'Dibandingkan periode sebelumnya dengan panjang yang sama.',
                'saran' => 'Kenaikan liter per ton sering mendahului kerusakan alat; periksa unit dengan konsumsi menyimpang.',
            ];
        }

        return $p;
    }
}
