<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Salin seluruh isi basis data SQLite ke PostgreSQL.
 *
 * Urutan kerja:
 *   1. `php artisan migrate --database=pgsql` — bangun skema kosong di Postgres
 *   2. `php artisan eq:pindah-pgsql`          — salin datanya
 *
 * Perintah ini hanya MEMBACA dari sqlite dan MENULIS ke pgsql.
 * Basis data SQLite tidak disentuh, jadi selalu bisa dijadikan cadangan.
 */
class PindahKePgsql extends Command
{
    protected $signature = 'eq:pindah-pgsql
                            {--dari=sqlite : koneksi sumber}
                            {--ke=pgsql : koneksi tujuan}
                            {--potong : kosongkan tabel tujuan sebelum menyalin}
                            {--batch=200 : jumlah baris per sisipan}';

    protected $description = 'Salin data dari SQLite ke PostgreSQL';

    /** Urutan aman: induk sebelum anak. Tabel di luar daftar disalin belakangan. */
    private array $urutan = [
        'migrations', 'companies', 'users', 'password_reset_tokens', 'sessions',
        'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
        'app_settings', 'activity_logs', 'signatories',
        'courses', 'modules', 'materials', 'enrollments', 'evaluations',
        'questions', 'attempts', 'certificates', 'news', 'procedures',
        'tpkkp_assessments', 'tpkkp_responses',
        'locations', 'hazard_reports', 'inspection_templates', 'inspection_template_items',
        'inspections', 'inspection_items', 'inspection_inspectors', 'kpi_targets',
        'ko_objects', 'ko_personnel', 'ko_reviews',
        'ko_safeguards', 'ko_inspections', 'ko_actions',
    ];

    public function handle(): int
    {
        $dari = $this->option('dari');
        $ke   = $this->option('ke');

        try {
            DB::connection($dari)->getPdo();
            DB::connection($ke)->getPdo();
        } catch (\Throwable $e) {
            $this->error('Koneksi gagal: ' . $e->getMessage());
            return self::FAILURE;
        }

        $semua = $this->daftarTabel($dari);
        $urut  = array_values(array_unique(array_merge(
            array_values(array_intersect($this->urutan, $semua)),
            array_values(array_diff($semua, $this->urutan))
        )));

        $this->info("Sumber : {$dari} · " . count($urut) . ' tabel');
        $this->info("Tujuan : {$ke}");
        $this->newLine();

        if ($this->option('potong')) {
            foreach (array_reverse($urut) as $t) {
                if (Schema::connection($ke)->hasTable($t)) {
                    DB::connection($ke)->statement('TRUNCATE TABLE "' . $t . '" RESTART IDENTITY CASCADE');
                }
            }
            $this->warn('Tabel tujuan dikosongkan.');
            $this->newLine();
        }

        $total = 0;
        $lewat = [];

        foreach ($urut as $t) {
            if (!Schema::connection($ke)->hasTable($t)) { $lewat[] = $t; continue; }

            $kolomTujuan = Schema::connection($ke)->getColumnListing($t);
            $n = 0;

            DB::connection($dari)->table($t)->orderBy(
                in_array('id', Schema::connection($dari)->getColumnListing($t), true) ? 'id' : $kolomTujuan[0]
            )->chunk((int) $this->option('batch'), function ($rows) use ($t, $ke, $kolomTujuan, &$n) {
                $isi = [];
                foreach ($rows as $r) {
                    $baris = [];
                    foreach ((array) $r as $k => $v) {
                        if (in_array($k, $kolomTujuan, true)) $baris[$k] = $v;
                    }
                    $isi[] = $baris;
                }
                if ($isi) {
                    DB::connection($ke)->table($t)->insert($isi);
                    $n += count($isi);
                }
            });

            $total += $n;
            $this->line(sprintf('  %-32s %6d baris', $t, $n));
        }

        // Postgres: setel ulang sequence agar id berikutnya tidak bentrok
        if (DB::connection($ke)->getDriverName() === 'pgsql') {
            $this->newLine();
            $this->info('Menyetel ulang sequence…');
            foreach ($urut as $t) {
                if (!Schema::connection($ke)->hasTable($t)) continue;
                if (!in_array('id', Schema::connection($ke)->getColumnListing($t), true)) continue;
                try {
                    DB::connection($ke)->statement(
                        "SELECT setval(pg_get_serial_sequence('\"{$t}\"','id'),"
                        . " COALESCE((SELECT MAX(id) FROM \"{$t}\"), 1), true)"
                    );
                } catch (\Throwable $e) {
                    // tabel tanpa sequence — abaikan
                }
            }
        }

        $this->newLine();
        $this->info("Selesai. {$total} baris disalin.");
        if ($lewat) $this->warn('Dilewati (tidak ada di tujuan): ' . implode(', ', $lewat));

        return self::SUCCESS;
    }

    private function daftarTabel(string $koneksi): array
    {
        return collect(Schema::connection($koneksi)->getTables())
            ->pluck('name')
            ->reject(fn ($t) => str_starts_with($t, 'sqlite_'))
            ->values()->all();
    }
}
