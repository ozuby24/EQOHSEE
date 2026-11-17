<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Rute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * ID di alamat SMKP dan Miners dibatasi delapan belas digit.
 *
 * Di SQLite dan MySQL ID yang kebesaran atau berupa teks hanya tidak
 * menemukan baris, tetapi PostgreSQL menolak kuerinya — dan halaman yang
 * semestinya 404 menjadi 500 (lihat App\Support\Rute). Pemeriksaan pola
 * di bawah menangkapnya di SQLite juga.
 */
class RuteIdSmkpMinersTest extends TestCase
{
    use RefreshDatabase;

    /** Parameter yang memang teks, bukan ID. */
    private const TEKS = ['bagian', 'elemen', 'jenis', 'pihak', 'rapat'];

    /** Pola yang tidak pernah melampaui bigint. */
    private const AMAN = [Rute::ID, '[0-9]{1,9}', '[0-9]{1,2}'];

    public function test_setiap_parameter_id_berpola_terbatas(): void
    {
        $diperiksa = 0;

        foreach (Route::getRoutes() as $r) {
            if (!preg_match('#^(smkp|miners|asisten/smkp)(/|$)#', $r->uri())) continue;

            foreach ($r->parameterNames() as $p) {
                if (in_array($p, self::TEKS, true)) continue;
                $diperiksa++;
                $this->assertContains($r->wheres[$p] ?? null, self::AMAN, "Parameter {{$p}} pada {$r->uri()} tidak dibatasi panjangnya.");
            }
        }

        $this->assertGreaterThan(150, $diperiksa);
    }

    public function test_id_kebesaran_atau_teks_menjadi_404(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $besar = '99999999999999999999999';

        foreach (["/smkp/{$besar}", '/smkp/abc', "/smkp/{$besar}/temuan", "/miners/mcu/{$besar}", "/miners/{$besar}", '/miners/abc',
                  "/miners/permit/{$besar}", "/miners/kartu/{$besar}", "/miners/mcu/orang/{$besar}/sertifikat"] as $url) {
            $this->get($url)->assertNotFound();
        }
    }
}
