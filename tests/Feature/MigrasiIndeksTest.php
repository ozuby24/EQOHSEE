<?php

namespace Tests\Feature;

use App\Support\Indeks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

/**
 * Indeks yang menopang batas perusahaan, dan migrasi yang memasangnya.
 *
 * Dua hal diuji di sini, dan keduanya adalah kegagalan yang tidak pernah
 * mengeluarkan pesan:
 *
 *   Indeks yang hilang tidak memunculkan galat apa pun — hanya halaman
 *   yang sedikit lebih lambat tiap bulan daripada bulan sebelumnya, pada
 *   pemasangan yang paling lama dipakai.
 *
 *   Migrasi yang jatuh di tengah meninggalkan sebagian tabel sudah
 *   berubah dan sisanya belum, tanpa satu pun keadaan tercatat sebagai
 *   "sudah".
 */
class MigrasiIndeksTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setiap tabel ber-company_id harus punya indeks yang DIAWALI kolom itu.
     *
     * MilikPerusahaan menambahkan `where company_id = ?` pada setiap kueri
     * ke tabel-tabel ini — scope global, bukan pilihan per kueri. Tanpa
     * indeks yang diawali company_id, tiap pembacaan memindai seluruh
     * tabel lalu membuang baris milik perusahaan lain.
     *
     * "Diawali" itu penting: indeks (tanggal, company_id) tidak menolong
     * kueri yang hanya menyaring company_id, dan dari daftar indeks ia
     * terlihat seolah menolong.
     */
    public function test_setiap_tabel_berperusahaan_punya_indeks_yang_diawali_company_id(): void
    {
        $tanpa = [];

        foreach ($this->tabel() as $tabel) {
            if (!Schema::hasColumn($tabel, 'company_id')) continue;

            if (!$this->diawaliCompanyId($tabel)) $tanpa[] = $tabel;
        }

        $this->assertSame([], $tanpa,
            'Tabel berikut dipindai penuh tiap kali batas perusahaan diterapkan: '
            .implode(', ', $tanpa));
    }

    /**
     * Migrasi kode-unik harus tahan dijalankan pada basis data yang
     * indeks lamanya sudah tidak ada.
     *
     * Bentuk sebelumnya membungkus `$b->dropUnique()` dengan try/catch dan
     * TIDAK menangkap apa pun: Blueprint hanya mencatat perintah, dan
     * perintahnya baru berjalan sesudah closure selesai — di luar
     * jangkauan try/catch. Migrasinya jatuh dengan pengaman yang tampak
     * terpasang rapi tepat di atas baris yang menjatuhkannya.
     */
    public function test_migrasi_kode_unik_tahan_bila_indeks_lamanya_tidak_ada(): void
    {
        $berkas = database_path('migrations/2026_08_16_000008_kode_unik_per_perusahaan.php');
        $this->assertFileExists($berkas);

        $migrasi = require $berkas;

        /* Keadaan yang dulu menjatuhkannya: indeks baru sudah ada, yang
           lama sudah lenyap. Menjalankan up() lagi di atas keadaan itu
           harus berlalu tanpa keluhan. */
        $this->assertTrue(Indeks::ada('hazard_reports', 'hazard_reports_company_unik'),
            'Prasyarat uji tidak terpenuhi: migrasinya belum berjalan.');

        $migrasi->up();

        $this->assertTrue(Indeks::ada('hazard_reports', 'hazard_reports_company_unik'),
            'Menjalankan ulang migrasi justru membuang indeksnya.');
    }

    /** Migrasi indeks pun harus aman diulang. */
    public function test_migrasi_indeks_aman_dijalankan_dua_kali(): void
    {
        $migrasi = require database_path('migrations/2026_08_18_000002_indeks_batas_perusahaan.php');

        $migrasi->up();
        $migrasi->up();

        $this->assertTrue(Indeks::ada('water_logs', 'water_logs_company_id_tanggal_index'));
    }

    /** Helper-nya sendiri harus menjawab benar untuk indeks yang tidak ada. */
    public function test_indeks_yang_tidak_ada_dijawab_tidak_ada(): void
    {
        $this->assertFalse(Indeks::ada('users', 'indeks_yang_tidak_pernah_dibuat'));
        $this->assertTrue(Indeks::ada('water_logs', 'water_logs_company_id_tanggal_index'));
    }

    /* ─────────── bantu ─────────── */

    /** @return list<string> */
    private function tabel(): array
    {
        return collect(Schema::getTables())->pluck('name')
            ->reject(fn ($n) => str_starts_with($n, 'sqlite_'))
            ->values()->all();
    }

    private function diawaliCompanyId(string $tabel): bool
    {
        foreach (DB::select('PRAGMA index_list("'.$tabel.'")') as $i) {
            $kolom = array_column(DB::select('PRAGMA index_info("'.$i->name.'")'), 'name');
            if (($kolom[0] ?? null) === 'company_id') return true;
        }

        return false;
    }
}
