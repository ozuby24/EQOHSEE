<?php

namespace App\Console\Commands;

use App\Support\Miners\SinkronMasaBerlaku;
use App\Support\Waktu;
use Illuminate\Console\Command;

/**
 * Sinkron masa berlaku modul Miners — `masa-berlaku:sync` dan
 * `app:update-mcu-status` Safe Track dalam satu perintah.
 *
 * Dijadwalkan setiap pagi (routes/console.php). Aman diulang: yang sudah
 * sinkron tidak disentuh lagi. Lihat App\Support\Miners\SinkronMasaBerlaku.
 */
class SinkronMiners extends Command
{
    protected $signature = 'miners:sinkron';

    protected $description = 'Selaraskan masa berlaku Mine Permit dengan MCU dan tandai Mine Permit serta MCU yang kedaluwarsa.';

    public function handle(): int
    {
        $this->info('Miners — sinkron masa berlaku '.Waktu::kini()->format('d-m-Y H:i'));

        $h = SinkronMasaBerlaku::jalankan();

        $this->line(sprintf('  %-34s : %d', 'Permit disamakan dengan MCU', $h['permit']));
        $this->line(sprintf('  %-34s : %d', 'Permit ditandai expired', $h['expired']));
        $this->line(sprintf('  %-34s : %d', 'Permit kembali terbit', $h['pulih']));
        $this->line(sprintf('  %-34s : %d', 'Surat MCU ditandai expired', $h['mcu']));
        $this->line(sprintf('  %-34s : %d', 'Surat MCU kembali selesai', $h['mcuPulih']));

        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
