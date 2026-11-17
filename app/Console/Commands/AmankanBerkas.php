<?php

namespace App\Console\Commands;

use App\Support\Berkas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Pindahkan berkas tertutup keluar dari disk publik.
 *
 * Kode yang baru sudah menyimpan ke disk tertutup. Yang tidak berpindah
 * sendiri adalah berkas yang SUDAH terlanjur ada — dan justru itulah
 * yang penting: dokumen terkendali, foto insiden, dan gambar tanda
 * tangan yang selama ini terbaca dari eqohsee.id/storage/… tanpa login.
 * Selama berkas itu masih di sana, memperbaiki kodenya tidak menutup apa
 * pun; ia hanya berhenti menambah.
 *
 * Dijalankan otomatis oleh deploy.sh. Aman diulang: berkas yang sudah
 * pindah dilewati, dan yang gagal dipindah TIDAK dihapus dari tempat
 * lamanya — kehilangan berkas lebih buruk daripada berkas yang masih
 * terbuka satu deploy lebih lama.
 */
class AmankanBerkas extends Command
{
    protected $signature = 'berkas:amankan {--kering : Tampilkan yang akan dipindah, tanpa memindah}';

    protected $description = 'Pindahkan dokumen, tanda tangan, dan foto dari disk publik ke disk tertutup';

    public function handle(): int
    {
        $publik  = Storage::disk(Berkas::TERBUKA);
        $tertutup = Storage::disk(Berkas::TERTUTUP);
        $kering  = (bool) $this->option('kering');

        $pindah = 0;
        $lewat  = 0;
        $gagal  = 0;

        foreach (Berkas::FOLDER_TERTUTUP as $folder) {
            if (!$publik->exists($folder)) continue;

            foreach ($publik->allFiles($folder) as $jalur) {
                if ($tertutup->exists($jalur)) {
                    /* Sudah ada di tujuan. Yang di sumber dibuang supaya
                       alamat lamanya berhenti menjawab — selama ia masih
                       ada, pemindahan ini belum menutup apa pun. */
                    if (!$kering) $publik->delete($jalur);
                    $lewat++;
                    continue;
                }

                if ($kering) { $this->line('  akan pindah: '.$jalur); $pindah++; continue; }

                $isi = $publik->get($jalur);

                if ($isi === null || !$tertutup->put($jalur, $isi)) {
                    $this->warn('  GAGAL: '.$jalur);
                    $gagal++;
                    continue;
                }

                /* Baru dihapus sesudah salinannya terbukti ada. */
                if ($tertutup->exists($jalur)) {
                    $publik->delete($jalur);
                    $pindah++;
                } else {
                    $gagal++;
                }
            }
        }

        $this->info($kering
            ? "Kering: $pindah berkas akan dipindah, $lewat sudah pindah."
            : "$pindah berkas dipindah, $lewat sudah pindah sebelumnya, $gagal gagal.");

        return $gagal > 0 ? self::FAILURE : self::SUCCESS;
    }
}
