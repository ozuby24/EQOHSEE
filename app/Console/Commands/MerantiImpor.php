<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Support\Meranti\Impor;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Muat lembar Google Sheets MERANTI ke satu perusahaan dari terminal.
 *
 *   php artisan meranti:impor /path/MERANTI.xlsx --perusahaan="Cemerlang Asa Mandiri"
 *   php artisan meranti:impor /path/Progres_Mingguan.csv --perusahaan=3
 *   php artisan meranti:impor --tautan="https://docs.google.com/spreadsheets/d/…" --perusahaan=3
 *
 * Aman diulang: baris yang sudah pernah dimuat diperbarui, bukan
 * digandakan. Lembar Users tidak pernah dimuat.
 */
class MerantiImpor extends Command
{
    protected $signature = 'meranti:impor
        {berkas? : Berkas .xlsx hasil unduhan Google Sheets, atau .csv ekspor MERANTI}
        {--tautan= : Tautan Google Sheets yang dibagikan "siapa saja yang memiliki link"}
        {--perusahaan= : ID atau nama perusahaan tujuan}
        {--kering : Tampilkan pratinjau saja, tanpa menyimpan}';

    protected $description = 'Impor data MERANTI (lembar Google Sheets) ke aplikasi MERANTI EQOHSEE';

    public function handle(): int
    {
        $c = $this->perusahaan((string) $this->option('perusahaan'));
        if (!$c) return self::FAILURE;

        $sementara = null;
        try {
            if ($t = $this->option('tautan')) {
                $sementara = Impor::unduhTautan((string) $t);
                $buku = Impor::bacaXlsx($sementara);
                $label = 'Google Sheets '.Impor::idLembar((string) $t);
            } elseif ($b = $this->argument('berkas')) {
                if (!is_file($b)) throw new RuntimeException("Berkas {$b} tidak ditemukan.");
                $buku = preg_match('/\.csv$/i', $b) ? Impor::bacaCsv($b, basename($b)) : Impor::bacaXlsx($b);
                $label = basename($b);
            } else {
                $this->error('Sebutkan sumbernya: jalur berkas .xlsx/.csv, atau --tautan=…');

                return self::FAILURE;
            }

            $this->info("Sumber: {$label}");
            $this->info("Tujuan: {$c->name} (#{$c->id})");
            $this->table(['Lembar', 'Baris', 'Masuk ke'], array_map(fn ($p) => [$p['lembar'], $p['baris'], $p['jenis'] === 'modul' || $p['jenis'] === 'log' ? $p['judul'] : '— '.$p['ket']], Impor::pratinjau($buku)));

            if ($this->option('kering')) {
                $this->comment('Mode kering: tidak ada yang disimpan.');

                return self::SUCCESS;
            }

            $hasil = Impor::jalankan($buku, $c->id, null, $label);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            if ($sementara && is_file($sementara)) @unlink($sementara);
        }

        $this->table(['Lembar', 'Modul', 'Baru', 'Diperbarui', 'Dilewati'], array_map(fn ($r) => [$r['lembar'], $r['judul'], $r['baru'], $r['diperbarui'], $r['dilewati']], $hasil['lembar']));
        foreach ($hasil['lembar'] as $r) foreach ($r['catatan'] ?? [] as $cat) if ($cat) $this->line("  · {$r['judul']}: {$cat}");
        $this->info("Selesai: {$hasil['baru']} baru, {$hasil['diperbarui']} diperbarui, {$hasil['dilewati']} dilewati.");

        return self::SUCCESS;
    }

    private function perusahaan(string $cari): ?Company
    {
        if ($cari === '') {
            $this->error('Sebutkan perusahaan tujuan: --perusahaan=ID atau --perusahaan="nama".');

            return null;
        }

        $q = Company::withoutGlobalScopes();
        if (ctype_digit($cari)) return $q->find((int) $cari) ?? tap(null, fn () => $this->error("Perusahaan #{$cari} tidak ada."));

        $cocok = $q->where('name', 'like', '%'.$cari.'%')->orderBy('id')->get();
        if ($cocok->count() === 1) return $cocok->first();

        $this->error($cocok->isEmpty() ? "Tidak ada perusahaan yang namanya memuat \"{$cari}\"." : 'Lebih dari satu perusahaan cocok — pakai ID-nya:');
        foreach ($cocok as $c) $this->line("  #{$c->id}  {$c->name}".($c->demo ? '  (contoh)' : ''));

        return null;
    }
}
