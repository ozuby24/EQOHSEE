<?php

namespace App\Console\Commands;

use App\Models\{SmkpAudit, SmkpFinding};
use App\Support\Smkp;
use Illuminate\Console\Command;

/**
 * Selaraskan ulang jenis (Mayor/Minor) CAR yang sudah diangkat dengan hasil
 * Smkp::temuan() terkini.
 *
 * Jenis CAR yang sudah tersimpan tidak otomatis mengikuti perubahan aturan
 * kategori (mis. "rincian tidak pernah Mayor bila sub-elemen Minor") —
 * selama ini hanya tersinkron saat auditor menyimpan ulang elemen terkait
 * (lihat SmkpController::selaraskanJenisTemuan). Dijalankan sekali setelah
 * aturan kategori berubah, supaya CAR lama yang belum disimpan ulang ikut
 * terkoreksi tanpa menunggu auditor membuka halaman penilaiannya lagi.
 *
 *   php artisan smkp:selaraskan-temuan --dry-run
 *   php artisan smkp:selaraskan-temuan
 */
class SelaraskanJenisTemuan extends Command
{
    protected $signature = 'smkp:selaraskan-temuan {--dry-run : Tampilkan saja tanpa mengubah data}';

    protected $description = 'Selaraskan jenis Mayor/Minor pada CAR terbuka dengan hasil penilaian terkini';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $total = 0;

        SmkpAudit::withoutGlobalScopes()->chunkById(50, function ($audits) use (&$total, $dry) {
            foreach ($audits as $audit) {
                $segar = collect(Smkp::temuan($audit->hasil ?? []))->keyBy('kode');

                $berubah = SmkpFinding::withoutGlobalScopes()
                    ->where('audit_id', $audit->id)
                    ->where('status', '<>', 'Closed')
                    ->whereIn('jenis', ['mayor', 'minor'])
                    ->get()
                    ->filter(fn (SmkpFinding $f) => $segar->has($f->kode_kriteria)
                        && $segar[$f->kode_kriteria]['jenis'] !== $f->jenis);

                foreach ($berubah as $f) {
                    $baru = $segar[$f->kode_kriteria]['jenis'];
                    $this->line(sprintf(
                        '  %s (%s) · %s: %s -> %s',
                        $audit->namaTampil(), $audit->tahun, $f->nomor ?: $f->kode_kriteria, $f->jenis, $baru
                    ));
                    if (!$dry) $f->update(['jenis' => $baru]);
                    $total++;
                }
            }
        });

        $this->info($dry
            ? "{$total} CAR akan diselaraskan (dry-run, belum disimpan)."
            : "{$total} CAR diselaraskan.");

        return self::SUCCESS;
    }
}
