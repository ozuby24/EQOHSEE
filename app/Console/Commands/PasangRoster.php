<?php

namespace App\Console\Commands;

use App\Support\Hr\{MasterCuti, MasterRoster};
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

    protected $description = 'Pasang daftar awal HRIS: pola roster dan jenis cuti.';

    /**
     * Jenis cuti ikut dipasang di sini, bukan lewat perintah kedua.
     *
     * Keduanya daftar awal bersama modul yang sama, dan deploy/deploy.sh
     * sudah memanggil perintah ini. Sebagai perintah tersendiri, ia
     * harus ditambahkan ke skrip penerapan — dan skrip yang lupa
     * diperbarui menghasilkan daftar pilih cuti yang kosong di server,
     * sementara di mesin penguji ia terisi.
     */
    public function handle(): int
    {
        $this->info('Memasang daftar awal HRIS…');

        foreach (MasterRoster::pasang() + MasterCuti::pasang() as $tabel => $jumlah) {
            $this->line(sprintf('  %-24s %5d baris', $tabel, $jumlah));
        }

        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
