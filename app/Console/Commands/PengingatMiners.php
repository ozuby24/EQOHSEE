<?php

namespace App\Console\Commands;

use App\Support\Miners\PengingatMasaBerlaku;
use App\Support\Waktu;
use Illuminate\Console\Command;

/**
 * Pengingat masa berlaku Miners — `Alert:MasaBerlaku` Safe Track.
 *
 * Dijadwalkan setiap pagi (routes/console.php). Aman diulang: dokumen
 * yang sudah diingatkan pada ambangnya tidak dikirim lagi. Lihat
 * App\Support\Miners\PengingatMasaBerlaku.
 */
class PengingatMiners extends Command
{
    protected $signature = 'miners:pengingat {--uji-coba : Hitung yang akan dikirim tanpa mengirim atau mencatat apa pun}';

    protected $description = 'Kirim pengingat surel dokumen MCU, Induksi, Mine Permit, dan SIMPER yang berakhir ≤ 60 hari atau sudah lewat.';

    public function handle(PengingatMasaBerlaku $pengingat): int
    {
        $uji = (bool) $this->option('uji-coba');
        $this->info('Miners — pengingat masa berlaku '.Waktu::kini()->format('d-m-Y H:i').($uji ? ' (UJI COBA)' : ''));

        $h = $pengingat->kirim($uji);

        $this->line(sprintf('  %-26s : %d', 'Surel '.($uji ? 'akan dikirim' : 'terkirim'), $h['surel']));
        $this->line(sprintf('  %-26s : %d', 'Dokumen diingatkan', $h['dokumen']));
        foreach ($h['tanpaPenerima'] as $n) {
            $this->warn("  Tanpa penerima: {$n} — isi MINERS_SUREL_OHSE atau HSE pada Alur Persetujuan IUP itu.");
        }
        foreach ($h['gagal'] as $g) $this->error('  Gagal: '.$g);

        return $h['gagal'] === [] ? self::SUCCESS : self::FAILURE;
    }
}
