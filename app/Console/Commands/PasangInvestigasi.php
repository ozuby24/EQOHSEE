<?php

namespace App\Console\Commands;

use App\Support\Investigasi\MasterInvestigasi;
use Illuminate\Console\Command;

/**
 * Pasang master data modul Investigasi.
 *
 * Dijalankan sesudah `migrate`, dan aman dijalankan berulang — matriks
 * risiko tetap 25 baris dan kamus SCAT tetap 252 butir berapa kali pun
 * perintah ini dipanggil. Idempotensinya diuji, bukan sekadar
 * dimaksudkan: seeder yang menggandakan membuat rekap "penyebab
 * terbanyak" menghitung tiap penyebab dua kali, dan itu jenis kesalahan
 * yang tidak pernah terlihat sampai seseorang membandingkannya dengan
 * hitungan tangan.
 */
class PasangInvestigasi extends Command
{
    protected $signature = 'investigasi:pasang';

    protected $description = 'Pasang master data modul Investigasi (matriks risiko, klasifikasi, kamus SCAT).';

    public function handle(): int
    {
        $this->info('Memasang master data Investigasi…');

        foreach (MasterInvestigasi::pasang() as $tabel => $jumlah) {
            $this->line(sprintf('  %-28s %5d baris', $tabel, $jumlah));
        }

        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
