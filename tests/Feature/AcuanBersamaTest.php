<?php

namespace Tests\Feature;

use App\Support\AcuanBersama;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

/**
 * Daftar tabel acuan bersama harus LENGKAP dan JUJUR.
 *
 * Lengkap: setiap tabel yang diisi perintah pemasangan tanpa perusahaan
 * tercantum — kalau tidak, Pemeriksaan Mandiri kembali membaca daftar
 * awal sebagai data lapangan yang kehilangan pemilik.
 *
 * Jujur: hanya tabel yang benar-benar ada dan berkolom company_id —
 * nama yang salah ketik atau tabel yang sudah dibuang adalah
 * pengecualian yang tidak mengecualikan apa pun, dan diam-diam.
 */
class AcuanBersamaTest extends TestCase
{
    use RefreshDatabase;

    public function test_setiap_tabel_yang_diisi_perintah_pemasangan_tercantum(): void
    {
        /* Sama dengan urutan deploy/deploy.sh, ditambah dua master yang
           ditanam pemuat data contoh. */
        foreach (['investigasi:pasang', 'pjp:pasang', 'miners:pasang', 'roster:pasang',
                  'energi:pasang', 'lca:pasang', 'pembelian:katalog'] as $perintah) {
            $this->artisan($perintah)->assertSuccessful();
        }
        \App\Support\MasterKompetensi::tanam();
        \App\Support\MasterUnitSpip::tanam();

        $abaikan = ['users', 'signatories', 'activity_log', 'procedures', 'news'];
        $lepas = [];

        foreach (Schema::getTables() as $t) {
            $tabel = is_array($t) ? $t['name'] : $t->name;

            if (in_array($tabel, $abaikan, true)) continue;
            if (! Schema::hasColumn($tabel, 'company_id')) continue;
            if (! DB::table($tabel)->whereNull('company_id')->exists()) continue;

            if (! AcuanBersama::termasuk($tabel)) $lepas[] = $tabel;
        }

        $this->assertSame([], $lepas,
            'Perintah pemasangan mengisi tabel ini tanpa perusahaan, tetapi tabelnya tidak '
            .'tercantum di App\Support\AcuanBersama — Pemeriksaan Mandiri akan membacanya '
            .'sebagai data lapangan tanpa pemilik.');
    }

    public function test_setiap_tabel_yang_tercantum_ada_dan_berperusahaan(): void
    {
        $salah = [];

        foreach (array_keys(AcuanBersama::TABEL) as $tabel) {
            if (! Schema::hasTable($tabel) || ! Schema::hasColumn($tabel, 'company_id')) $salah[] = $tabel;
        }

        $this->assertSame([], $salah, 'Tabel acuan bersama ini tidak ada atau tidak berkolom company_id.');
    }
}
