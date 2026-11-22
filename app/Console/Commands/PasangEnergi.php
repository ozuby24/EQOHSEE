<?php

namespace App\Console\Commands;

use App\Models\Energi\Faktor;
use App\Support\Energi\PaketFaktor;
use Illuminate\Console\Command;

/**
 * Pasang paket faktor energi & emisi bersama (FSD §17, §14 M3).
 *
 * Aman dijalankan setiap deploy: hanya menambah yang belum ada. Faktor yang
 * masih bertanda "perlu dicocokkan" disebut di akhir supaya yang menjalankan
 * penerapan tahu laporan resmi belum boleh disampaikan dengan angka itu.
 */
class PasangEnergi extends Command
{
    protected $signature = 'energi:pasang';

    protected $description = 'Pasang paket faktor energi & emisi acuan (IPCC 2006, GWP AR5/AR6, campuran B-xx, TOE).';

    public function handle(): int
    {
        $h = PaketFaktor::pasang();

        $this->info("Registri faktor energi: {$h['baru']} baru, {$h['ada']} sudah ada.");

        $belum = Faktor::withoutGlobalScopes()->whereNull('company_id')
            ->where('status', 'aktif')->where('perlu_cocok', true)
            ->orderBy('kode_energi')->get(['kode_energi', 'parameter', 'kategori_ipcc']);

        if ($belum->isNotEmpty()) {
            $this->newLine();
            $this->warn('Faktor berikut masih nilai acuan dan PERLU DICOCOKKAN dengan dokumen sumbernya:');
            foreach ($belum as $f) {
                $this->line('  · '.$f->kode_energi.' / '.$f->parameter.($f->kategori_ipcc ? ' ('.$f->kategori_ipcc.')' : ''));
            }
            $this->line('  Cocokkan di /karbon/faktor sebelum laporan resmi disampaikan.');
        }

        return self::SUCCESS;
    }
}
