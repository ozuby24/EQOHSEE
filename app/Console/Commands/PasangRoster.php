<?php

namespace App\Console\Commands;

use App\Support\Hr\MasterRoster;
use Illuminate\Console\Command;

/**
 * Pasang pola roster awal bersama.
 *
 * Dijalankan sesudah `migrate` pada tiap penerapan, dan aman diulang:
 * baris yang sudah ada hanya diperbarui pada bentuk siklusnya, tidak
 * pada namanya, dan tidak satu pun dihapus — `hr_regu.pola_roster_id`
 * menunjuk ke sini.
 */
class PasangRoster extends Command
{
    protected $signature = 'roster:pasang';

    protected $description = 'Pasang pola roster awal bersama (14:7, 10:2 minggu, dan seterusnya).';

    public function handle(): int
    {
        $this->info('Memasang pola roster…');

        foreach (MasterRoster::pasang() as $tabel => $jumlah) {
            $this->line(sprintf('  %-24s %5d baris', $tabel, $jumlah));
        }

        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
