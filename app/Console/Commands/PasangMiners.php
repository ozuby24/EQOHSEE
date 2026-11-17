<?php

namespace App\Console\Commands;

use App\Support\Miners\MasterMiners;
use Illuminate\Console\Command;

/**
 * Pasang daftar awal bersama modul Miners.
 *
 * Dijalankan sesudah `migrate` pada tiap penerapan, dan aman diulang:
 * baris yang sudah ada hanya diperbarui pada kolom yang berasal dari
 * SOP, tidak pada namanya, dan tidak satu pun dihapus. Keduanya diuji —
 * lihat tests/Feature/MinersMasterTest.php — bukan sekadar dimaksudkan.
 */
class PasangMiners extends Command
{
    protected $signature = 'miners:pasang';

    protected $description = 'Pasang daftar awal bersama modul Miners (departemen, lokasi, golongan unit, jenis permit, hasil MCU).';

    public function handle(): int
    {
        $this->info('Memasang daftar awal Miners…');

        foreach (MasterMiners::pasang() as $tabel => $jumlah) {
            $this->line(sprintf('  %-24s %5d baris', $tabel, $jumlah));
        }

        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
