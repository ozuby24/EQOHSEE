<?php

namespace Tests\Feature;

use App\Models\{Pjp, PjpEvaluasi, PjpLaporan, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Uji asap halaman modul PJP, termasuk yang berparameter.
 *
 * AsapRuteTest melewati rute ber-{parameter} karena isinya bergantung
 * pada data yang harus disiapkan tiap modul sendiri — maka halaman
 * detail, checklist, formulir ubah, dan lembar cetak tidak pernah
 * tersentuh jaring pengaman itu. Tiga di antaranya adalah halaman
 * terbesar modul ini.
 */
class PjpAsapTest extends TestCase
{
    use RefreshDatabase;

    public function test_seluruh_halaman_modul_terbuka_dengan_data_terisi(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $pjp = Pjp::factory()->create();
        PjpLaporan::factory()->for($pjp)->create();
        PjpEvaluasi::factory()->for($pjp)->create();

        $halaman = [
            route('pjp.index'),
            route('pjp.persyaratan'),
            route('pjp.pelaporan'),
            route('pjp.evaluasi'),
            route('pjp.daftar'),
            route('pjp.baru'),
            route('pjp.bantuan'),
            route('pjp.detail', $pjp),
            route('pjp.ubah', $pjp),
            route('pjp.checklist', $pjp),
            route('pjp.cetak', $pjp),
            route('pjp.ekspor'),
        ];

        foreach ($halaman as $url) {
            $this->get($url)->assertOk();
        }
    }
}
