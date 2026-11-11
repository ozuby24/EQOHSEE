<?php

namespace App\Console\Commands;

use App\Models\Miners\Simper;
use App\Support\Miners\DeteksiMasaSim;
use Illuminate\Console\Command;

/**
 * Deteksi masa berlaku SIM untuk data LAMA — `masa-berlaku:deteksi-sim`
 * Safe Track.
 *
 * Deteksi hanya berjalan saat berkas diunggah lewat formulir, sehingga
 * SIMPER yang sudah ada sebelum fitur itu tetap tanpa tanggal SIM. Perintah
 * ini membaca ulang berkas SIM yang tersimpan.
 *
 * Bedanya dengan Safe Track: hasilnya hanya USULAN. Tanggal tidak pernah
 * ditulis ke `simpol_berlaku_sampai` — usulan disimpan di
 * `simpol_meta.usulan` dan tampil di halaman detail SIMPER, tempat admin
 * menerapkannya lewat tombol "Masa berlaku SIM" yang sudah ada. SIMPER
 * yang tanggalnya sudah diisi (manual, otomatis, atau perpanjangan) tidak
 * disentuh sama sekali.
 */
class DeteksiSimMiners extends Command
{
    protected $signature = 'miners:deteksi-sim
                            {--limit=0 : Batasi jumlah baris yang diproses (0 = semua)}
                            {--ulangi : Baca ulang juga yang sudah punya usulan}
                            {--uji-coba : Tampilkan hasil tanpa menyimpan usulan}';

    protected $description = 'Usulkan masa berlaku SIM dari berkas SIM tersimpan untuk SIMPER lama yang belum bertanggal SIM (tidak menimpa apa pun).';

    public function handle(): int
    {
        $q = Simper::withoutGlobalScopes()
            ->whereNull('simpol_berlaku_sampai')
            ->whereNotNull('berkas_simpol')->where('berkas_simpol', '<>', '')
            ->orderBy('id');
        if ((int) $this->option('limit') > 0) $q->limit((int) $this->option('limit'));

        $n = ['diproses' => 0, 'diusulkan' => 0, 'tidak_terbaca' => 0, 'dilewati' => 0];
        $uji = (bool) $this->option('uji-coba');

        foreach ($q->get() as $s) {
            $meta = (array) ($s->simpol_meta ?? []);
            if (isset($meta['usulan']) && ! $this->option('ulangi')) {
                $n['dilewati']++;
                continue;
            }
            $n['diproses']++;
            $h = DeteksiMasaSim::dariJalur($s->berkas_simpol);
            if (empty($h['tanggal'])) {
                $n['tidak_terbaca']++;
                continue;
            }
            $n['diusulkan']++;
            $this->line(sprintf('  %-22s %s (%s)', $s->no_simper ?: '#'.$s->id, $h['tanggal'], $h['keyakinan']));
            if (! $uji) {
                $meta['usulan'] = $h + ['pada' => now()->toIso8601String()];
                /* Tulis langsung kolomnya — tanpa menyentuh tanggal SIM
                   dan tanpa memicu sinkron masa berlaku. */
                Simper::withoutGlobalScopes()->whereKey($s->id)->update(['simpol_meta' => json_encode($meta)]);
            }
        }

        foreach ($n as $k => $v) $this->line(sprintf('  %-14s : %d', str_replace('_', ' ', $k), $v));
        $this->comment('Usulan tampil di detail SIMPER; terapkan lewat "Masa berlaku SIM". Tanggal yang sudah diisi tidak pernah ditimpa.');

        return self::SUCCESS;
    }
}
