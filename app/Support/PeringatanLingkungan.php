<?php

namespace App\Support;

use App\Models\LingkunganArea;
use App\Models\LingkunganPantau;
use Illuminate\Support\Collection;

/**
 * Peringatan pengelolaan lingkungan dan reklamasi.
 *
 * Sifat waktunya berbeda dari modul lain, dan itu menentukan bentuk
 * peringatannya. Luapan kolam datang dalam hitungan jam; lereng bergerak
 * dalam hitungan hari; tunggakan reklamasi menumpuk dalam hitungan tahun,
 * tanpa satu pun hari yang terasa mendesak.
 *
 * Karena itu yang diperingatkan di sini bukan kejadian melainkan arah:
 * nisbah reklamasi yang di bawah satu, petak yang menganggur melewati
 * batas, dan jaminan yang tertinggal dari kewajibannya. Ketiganya masih
 * dapat diperbaiki ketika terlihat, dan tidak lagi ketika izin diperiksa.
 */
final class PeringatanLingkungan
{
    public const TINGGI = 'tinggi';
    public const SEDANG = 'sedang';

    /**
     * @param Collection<int,LingkunganArea> $area
     * @param Collection<int,LingkunganPantau> $pantau hasil uji yang sudah disetujui
     * @return list<array{kode:string,level:string,judul:string,ket:string,saran:string}>
     */
    public static function susun(
        Collection $area,
        Collection $pantau,
        array $neraca,
        ?float $nisbah,
        array $jaminan,
        float $tumbuhMinimum = NeracaLahan::TUMBUH_MINIMUM_PERSEN,
        int $tunggakanWajarHari = NeracaLahan::TUNGGAKAN_WAJAR_HARI,
    ): array {
        $p = [];

        // Ketiadaan petak diperingatkan, tetapi tidak menghentikan sisanya.
        // Pemantauan mutu berdiri sendiri: perusahaan yang belum mendaftarkan
        // petak lahan tetap punya titik penaatan, dan pelanggaran baku mutu
        // di sana tidak boleh ikut hilang hanya karena daftar lahannya kosong.
        if ($area->isEmpty()) {
            $p[] = [
                'kode'  => 'tanpa-area',
                'level' => self::TINGGI,
                'judul' => 'Belum ada petak lahan terdaftar',
                'ket'   => 'Neraca lahan dan kewajiban reklamasi tidak dapat dihitung tanpa daftar petak terganggu.',
                'saran' => 'Daftarkan petak bukaan, timbunan, jalan, dan fasilitas beserta luas serta tanggal bukaannya.',
            ];
        }

        /* ---------- arah reklamasi ---------- */

        if ($nisbah !== null && $nisbah < 1.0) {
            $p[] = [
                'kode'  => 'nisbah-reklamasi-kurang',
                'level' => $nisbah < 0.5 ? self::TINGGI : self::SEDANG,
                'judul' => 'Nisbah reklamasi '.number_format($nisbah, 2).' — tunggakan bertambah',
                'ket'   => 'Luas yang diselesaikan lebih kecil daripada yang dibuka pada periode ini, '
                           .'sehingga lahan terganggu bertambah meski reklamasi berjalan.',
                'saran' => 'Selaraskan rencana bukaan dengan kapasitas reklamasi, atau tambah kapasitas penataan lahan.',
            ];
        }

        if (($neraca['telat'] ?? 0) > 0) {
            $p[] = [
                'kode'  => 'lahan-menganggur-telat',
                'level' => self::TINGGI,
                'judul' => number_format($neraca['telat'], 2).' ha menganggur melewati '.$tunggakanWajarHari.' hari',
                'ket'   => 'Terlama '.($neraca['umurTerlama'] ?? 0).' hari sejak penambangan di petaknya berhenti.',
                'saran' => 'Jadwalkan penataan lahan pada petak terlama lebih dulu; umur tunggakan menjadi temuan pada pemeriksaan.',
            ];
        }

        if (($neraca['menunggu'] ?? 0) > 0) {
            $p[] = [
                'kode'  => 'lahan-belum-disentuh',
                'level' => self::SEDANG,
                'judul' => number_format($neraca['menunggu'], 2).' ha berhenti ditambang tetapi belum disentuh',
                'ket'   => 'Belum ada satu pun tahapan reklamasi yang dimulai pada petak-petak ini.',
                'saran' => 'Tetapkan urutan penataan lahannya; ini ditagih ke perencana tambang, bukan ke pelaksana reklamasi.',
            ];
        }

        /* ---------- keberhasilan revegetasi ---------- */

        foreach ($area as $a) {
            $k = $a->kemajuanTerakhir();
            $tumbuh = $k?->tingkat_tumbuh_persen;

            if ($tumbuh !== null && $tumbuh < $tumbuhMinimum) {
                $p[] = [
                    'kode'  => 'tumbuh-kurang-'.$a->id,
                    'level' => self::TINGGI,
                    'judul' => "{$a->kode}: tingkat tumbuh ".number_format($tumbuh, 1).' %',
                    'ket'   => 'Di bawah '.number_format($tumbuhMinimum, 0).' % yang dipakai sebagai ambang keberhasilan.',
                    'saran' => 'Lakukan penyulaman dan periksa mutu tanah pucuk; reklamasi tidak dinilai berhasil pada tingkat tumbuh ini.',
                ];
            }

            $sisa = $a->sisaHariRencana();
            if ($sisa !== null && $sisa < 0) {
                $p[] = [
                    'kode'  => 'rencana-terlewat-'.$a->id,
                    'level' => self::SEDANG,
                    'judul' => "{$a->kode} terlewat rencana penyelesaian ".abs($sisa).' hari',
                    'ket'   => 'Masih pada tahapan '.(Reklamasi::TAHAP[$a->tahap] ?? $a->tahap).'.',
                    'saran' => 'Perbarui jadwal reklamasi pada rencana tahunan, atau kejar ketertinggalannya.',
                ];
            }
        }

        /* ---------- jaminan ---------- */

        if (($jaminan['cukup'] ?? true) === false) {
            $p[] = [
                'kode'  => 'jaminan-kurang',
                'level' => self::TINGGI,
                'judul' => 'Jaminan reklamasi kurang '.self::rupiah(abs((float) $jaminan['selisih'])),
                'ket'   => 'Ditempatkan '.self::rupiah((float) $jaminan['ditempatkan'])
                           .' dari kebutuhan '.self::rupiah((float) $jaminan['butuh']).'.',
                'saran' => 'Ajukan penempatan tambahan; kekurangan jaminan menahan persetujuan rencana kerja berikutnya.',
            ];
        }

        /* ---------- baku mutu ---------- */

        foreach (self::pelanggaranMutu($pantau) as $q) $p[] = $q;

        return $p;
    }

    /** @param Collection<int,LingkunganPantau> $pantau */
    private static function pelanggaranMutu(Collection $pantau): array
    {
        $langgar = $pantau->filter(fn (LingkunganPantau $x) => $x->melanggar());

        if ($langgar->isEmpty()) return [];

        $p = [];

        foreach ($langgar->groupBy('lingkungan_parameter_id') as $baris) {
            $satu = $baris->first();
            $par = $satu->parameter;
            $titik = $baris->pluck('titik')->unique()->implode(', ');

            $p[] = [
                'kode'  => 'baku-mutu-'.$satu->lingkungan_parameter_id,
                'level' => self::TINGGI,
                'judul' => ($par?->nama ?? 'Parameter').' melampaui baku mutu pada '.$baris->count().' hasil uji',
                // Ambangnya disebutkan beserta dasar hukumnya: yang
                // membaca perlu tahu angka mana yang sedang dipakai,
                // sebab angka itu dapat berbeda antar izin.
                'ket'   => 'Ambang '.($par?->rentangLabel() ?? '—')
                           .($par?->acuan ? ' menurut '.$par->acuan : '')
                           .'. Titik: '.$titik.'.',
                'saran' => 'Telusuri sumbernya dan laporkan sesuai ketentuan pelaporan lingkungan yang berlaku.',
            ];
        }

        return $p;
    }

    private static function rupiah(float $n): string
    {
        return 'Rp '.number_format($n, 0, ',', '.');
    }
}
