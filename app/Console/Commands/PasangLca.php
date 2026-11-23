<?php

namespace App\Console\Commands;

use App\Support\Lca\Pustaka;
use Illuminate\Console\Command;

/** Pasang pustaka faktor dampak LCA bawaan — aman diulang. */
class PasangLca extends Command
{
    protected $signature = 'lca:pasang';

    protected $description = 'Pasang atau perbarui pustaka faktor dampak LCA bersama (faktor yang sudah dicocokkan tidak ditimpa).';

    public function handle(): int
    {
        $n = Pustaka::pasang();
        $this->info("{$n} faktor pustaka LCA dipasang/diperbarui.");
        $this->line('  Nilai bawaan bertanda "perlu dicocokkan" — periksa di /lca/faktor sebelum studi diverifikasi.');

        return self::SUCCESS;
    }
}
