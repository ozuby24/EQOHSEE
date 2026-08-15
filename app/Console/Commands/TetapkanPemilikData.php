<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Menetapkan pemilik bagi data yang company_id-nya masih kosong.
 *
 * Data yang dibuat sebelum penempatan perusahaan ada tidak bertuan.
 * Selama tetap begitu, ia terlihat oleh SEMUA perusahaan — bukan karena
 * salah aturan, melainkan karena memang belum ada yang memilikinya.
 * Untuk data yang sebenarnya milik satu perusahaan, itu kebocoran yang
 * hanya dapat diselesaikan dengan menetapkan pemiliknya.
 *
 * Tidak dijalankan otomatis lewat migrasi dengan sengaja: menebak
 * pemilik data operasional adalah keputusan yang harus diambil orang
 * yang tahu jawabannya, bukan oleh kode yang menerka dari urutan id.
 */
class TetapkanPemilikData extends Command
{
    protected $signature = 'eqohsee:tetapkan-pemilik
                            {perusahaan? : Id perusahaan tujuan}
                            {--tabel=* : Batasi ke tabel tertentu}
                            {--terapkan : Benar-benar menyimpan; tanpa ini hanya melaporkan}';

    protected $description = 'Menetapkan perusahaan bagi baris yang company_id-nya masih kosong';

    /** Tabel yang barisnya memang dimiliki satu perusahaan. */
    private const TABEL = [
        'hazard_reports', 'inspections', 'documents', 'smkp_audits',
        'ko_objects', 'ko_personnel', 'gudang_lokasi',
        'energy_power_logs', 'energy_equipment', 'energy_baselines', 'energy_opportunities',
    ];

    public function handle(): int
    {
        $tabel = $this->option('tabel') ?: self::TABEL;
        $asing = array_diff($tabel, self::TABEL);

        if ($asing) {
            $this->error('Tabel di luar daftar: '.implode(', ', $asing));

            return self::FAILURE;
        }

        $yatim = [];
        foreach ($tabel as $t) {
            $n = DB::table($t)->whereNull('company_id')->count();
            if ($n) $yatim[$t] = $n;
        }

        if (!$yatim) {
            $this->info('Tidak ada baris tanpa pemilik. Tidak ada yang perlu dikerjakan.');

            return self::SUCCESS;
        }

        $this->table(['Tabel', 'Baris tanpa pemilik'],
            collect($yatim)->map(fn ($n, $t) => [$t, $n])->values()->all());

        $id = $this->argument('perusahaan');

        if (!$id) {
            $this->newLine();
            $this->line('Perusahaan terdaftar:');
            foreach (Company::orderBy('id')->get(['id', 'name']) as $c) {
                $this->line("  {$c->id}  {$c->name}");
            }
            $this->newLine();
            $this->comment('Sebutkan id perusahaan tujuan, lalu ulangi dengan --terapkan.');

            return self::SUCCESS;
        }

        $c = Company::find($id);

        if (!$c) {
            $this->error("Perusahaan id {$id} tidak ada.");

            return self::FAILURE;
        }

        if (!$this->option('terapkan')) {
            $this->newLine();
            $this->comment("Uji coba. Seluruh baris di atas akan menjadi milik: {$c->name}");
            $this->comment('Ulangi dengan --terapkan untuk menyimpannya.');

            return self::SUCCESS;
        }

        $jumlah = 0;
        DB::transaction(function () use ($yatim, $c, &$jumlah) {
            foreach (array_keys($yatim) as $t) {
                $jumlah += DB::table($t)->whereNull('company_id')->update(['company_id' => $c->id]);
            }
        });

        $this->info("{$jumlah} baris kini menjadi milik {$c->name}.");

        return self::SUCCESS;
    }
}
