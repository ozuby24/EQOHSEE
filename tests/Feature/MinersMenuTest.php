<?php

namespace Tests\Feature;

use App\Support\Menu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Concerns\SiapanMiners;
use Tests\TestCase;

/** Setiap entri menu Miners membuka halaman yang bekerja — tidak ada entri yang menunjuk ke mana-mana. */
class MinersMenuTest extends TestCase
{
    use RefreshDatabase;
    use SiapanMiners;

    #[Test]
    public function setiap_entri_menu_miners_terbuka(): void
    {
        $this->siapkan();
        $this->simperTerbit();
        $n = 0;

        foreach (Menu::all()['miners']['groups'] as $kelompok => $entri) {
            foreach ($entri as $e) {
                $this->actingAs($this->admin)->get(route($e[1]))->assertOk();
                $n++;
            }
        }
        $this->assertGreaterThan(30, $n);
    }
}
