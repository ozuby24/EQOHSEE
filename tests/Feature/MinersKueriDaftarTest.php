<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/**
 * Daftar Mine Permit, SIMPER, dan perpanjangan permit tidak menjalankan
 * satu kueri per baris — `perpanjanganBerjalan` (dibaca habisEfektif())
 * dimuat bersama, seperti DasborTest untuk dasbor.
 */
class MinersKueriDaftarTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkan();
    }

    private function hitung(string $url): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->admin)->get($url)->assertOk();
        $n = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $n;
    }

    #[Test]
    public function kueri_daftar_tidak_tumbuh_bersama_baris(): void
    {
        $this->simperTerbit();
        $alamat = ['/miners/riwayat/mine-permit', '/miners/riwayat/mine-license', '/miners/perpanjangan-permit', '/miners/daftar/cetak-kartu'];
        $kecil  = array_map(fn ($a) => $this->hitung($a), $alamat);

        for ($i = 0; $i < 5; $i++) $this->simperTerbit();
        $besar = array_map(fn ($a) => $this->hitung($a), $alamat);

        foreach ($alamat as $i => $a) {
            $this->assertLessThanOrEqual($kecil[$i] + 2, $besar[$i], "{$a}: {$kecil[$i]} kueri lalu {$besar[$i]} sesudah lima baris lagi.");
        }
    }
}
