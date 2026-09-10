<?php

namespace App\Console\Commands;

use App\Support\Pjp\DaftarPeriksaSmkp;
use Illuminate\Console\Command;

/**
 * Pasang daftar periksa prakualifikasi SMKP modul PJP.
 *
 * Dijalankan sesudah `migrate`, dan aman dijalankan berulang — 17
 * kategori tetap 17 dan 126 butir tetap 126 berapa kali pun dipanggil,
 * dan jawaban yang sudah tersimpan tidak ikut terhapus. Keduanya diuji,
 * bukan sekadar dimaksudkan.
 */
class PasangPjp extends Command
{
    protected $signature = 'pjp:pasang';

    protected $description = 'Pasang daftar periksa prakualifikasi SMKP untuk modul PJP.';

    public function handle(): int
    {
        $this->info('Memasang daftar periksa prakualifikasi SMKP…');

        foreach (DaftarPeriksaSmkp::pasang() as $tabel => $jumlah) {
            $this->line(sprintf('  %-24s %5d baris', $tabel, $jumlah));
        }

        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
