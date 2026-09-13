<?php

namespace App\Console\Commands;

use App\Support\Pjp\DaftarPeriksaSmkp;
use Illuminate\Console\Command;

/**
 * Pasang daftar periksa prakualifikasi SMKP modul PJP.
 *
 * Dipanggil deploy/deploy.sh pada SETIAP deploy, sesudah `migrate`, dan
 * itu disengaja. Migrasi menanam daftarnya sekali seumur hidup basis
 * data; begitu tercatat, checklist-smkp.json yang direvisi — pertanyaan
 * yang dibetulkan, bobot yang berubah mengikuti regulasi baru — tidak
 * akan pernah sampai ke produksi, dan servernya berjalan dengan daftar
 * versi lama tanpa satu pun tanda.
 *
 * Aman dijalankan berulang: daftarnya tetap 17 kategori dan 126 butir
 * berapa kali pun perintah ini dipanggil, dan tidak satu baris pun
 * dihapus — jawaban tiap mitra menunjuk butirnya lewat kunci asing yang
 * cascade on delete. Keduanya diuji di PasangPjpTest, bukan sekadar
 * dimaksudkan.
 */
class PasangPjp extends Command
{
    protected $signature = 'pjp:pasang';

    protected $description = 'Pasang daftar periksa prakualifikasi SMKP modul PJP (17 kategori, 126 pertanyaan).';

    public function handle(): int
    {
        $this->info('Memasang daftar periksa SMKP modul PJP…');

        $hasil = DaftarPeriksaSmkp::pasang();

        $this->line(sprintf('  %-28s %5d baris', 'smkp_checklist_categories', $hasil['kategori']));
        $this->line(sprintf('  %-28s %5d baris', 'smkp_checklist_items', $hasil['butir']));
        $this->line(sprintf('  %-28s %5d baris', 'baru ditanam', $hasil['baru']));
        $this->line(sprintf('  %-28s %5d baris', 'diperbarui', $hasil['diperbarui']));

        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
