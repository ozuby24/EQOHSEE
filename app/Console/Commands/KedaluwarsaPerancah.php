<?php

namespace App\Console\Commands;

use App\Models\Perancah\Unit;
use App\Support\Perancah\Penanda;
use Illuminate\Console\Command;

/**
 * Turunkan tag hijau/kuning yang lewat masa berlakunya menjadi merah.
 *
 * Tampilan sudah membaca tag semacam itu sebagai KEDALUWARSA tanpa
 * menunggu perintah ini. Yang ditambahkan di sini adalah jejaknya: tag
 * merah yang tercatat, dengan jam dan alasannya, sehingga riwayat unit
 * memperlihatkan kapan tepatnya perancah itu berhenti layak digunakan.
 */
class KedaluwarsaPerancah extends Command
{
    protected $signature = 'perancah:kedaluwarsa {--pratinjau : Sebutkan unitnya tanpa menerbitkan tag}';

    protected $description = 'Terbitkan tag merah untuk perancah yang inspeksinya lewat 7 hari.';

    public function handle(): int
    {
        $unit = Unit::withoutGlobalScopes()->whereIn('status_tag', ['hijau', 'kuning'])
            ->whereNotNull('inspeksi_berikut')->where('inspeksi_berikut', '<', now())->orderBy('id')->get();

        foreach ($unit as $u) {
            $this->line(sprintf('  %s  %s  (lewat %s)', $u->kode, $u->judul, $u->inspeksi_berikut->diffForHumans()));
            if (!$this->option('pratinjau')) Penanda::kedaluwarsakan($u);
        }

        $this->info($unit->count().' perancah '.($this->option('pratinjau') ? 'akan' : 'sudah').' ditandai merah kedaluwarsa.');

        return self::SUCCESS;
    }
}
