<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Services\PengingatTindakLanjut;
use Illuminate\Console\Command;

/**
 * Kirim email pengingat batas waktu tindak lanjut temuan yang jatuh tempo
 * hari ini (App\Services\PengingatTindakLanjut). Dijalankan penjadwal
 * setiap hari (routes/console.php); aman dijalankan ulang — pengingat yang
 * sudah terkirim tidak dikirim lagi.
 */
class KirimPengingatTindakLanjut extends Command
{
    protected $signature = 'smkp:pengingat-tindak-lanjut {--pratinjau : Tampilkan pengingat yang jatuh tempo tanpa mengirim}';

    protected $description = 'Kirim email pengingat batas waktu tindak lanjut temuan ke akun auditi';

    public function handle(PengingatTindakLanjut $pengingat): int
    {
        if ($this->option('pratinjau')) {
            $baris = [];
            foreach ($pengingat->jatuhTempo() as $g) {
                foreach ($g['butir'] as $b) {
                    $baris[] = [$g['perusahaan']->name, $b['temuan']->nomor ?: $b['temuan']->kode_kriteria, $b['temuan']->target_selesai->toDateString(),
                        PengingatTindakLanjut::JADWAL[$b['kode']], $g['penerima']->pluck('email')->implode(', ') ?: '— belum ada akun auditi —'];
                }
            }
            $baris ? $this->table(['Perusahaan', 'Temuan', 'Target', 'Jadwal', 'Penerima'], $baris) : $this->info('Tidak ada pengingat yang jatuh tempo hari ini.');

            return self::SUCCESS;
        }

        if (!PengingatTindakLanjut::aktif()) {
            $this->info('Pengingat tindak lanjut dimatikan (Admin → Pengingat Tindak Lanjut).');

            return self::SUCCESS;
        }
        if (!PengingatTindakLanjut::suratSiap()) {
            $this->warn('Server email belum diatur (MAIL_* di .env) — pengingat tidak dikirim.');

            return self::SUCCESS;
        }

        $h = $pengingat->kirim();
        $ringkas = self::ringkasan($h);
        $this->info($ringkas);
        if ($h['email'] || $h['gagal'] || $h['tanpaPenerima']) ActivityLog::write('Pengingat tindak lanjut', $ringkas, 'pengingat');

        return $h['gagal'] ? self::FAILURE : self::SUCCESS;
    }

    /** @param array{email:int, temuan:int, tanpaPenerima:list<string>, gagal:list<string>} $h */
    public static function ringkasan(array $h): string
    {
        $teks = "{$h['email']} email terkirim untuk {$h['temuan']} temuan";
        if ($h['tanpaPenerima']) $teks .= ' · belum ada akun auditi: '.implode(', ', $h['tanpaPenerima']);
        if ($h['gagal']) $teks .= ' · gagal: '.implode('; ', $h['gagal']);

        return mb_substr($teks, 0, 500);
    }
}
