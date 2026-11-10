<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\DataGrup;
use Tests\TestCase;

/**
 * Unsur keempat butir menu (HandleInertiaRequests::menu) sesudah
 * penggabungan SMKP + Miners: `true` = administrator saja,
 * 'kelolaMiners' = AksesMiners::kelola(), selain itu nama gerbang.
 */
class MenuSyaratTest extends TestCase
{
    use RefreshDatabase, DataGrup;

    protected function setUp(): void
    {
        parent::setUp();
        $this->siapkanGrup();
    }

    private function label(User $u, string $url): array
    {
        $res = $this->actingAs($u)->get($url);
        $res->assertOk();
        $label = [];
        foreach ($res->viewData('page')['props']['menu']['grup'] as $g) {
            foreach ($g['butir'] as $b) $label[] = $b['label'];
        }

        return $label;
    }

    public function test_syarat_true_hanya_administrator(): void
    {
        $biasa = User::factory()->create(['company_id' => $this->induk->id]);

        $this->assertContains('Daftar Harga', $this->label($this->admin(), '/pembelian'));
        $this->assertNotContains('Daftar Harga', $this->label($biasa, '/pembelian'));
    }

    public function test_syarat_kelola_miners_bagi_admin_dan_hse_iup(): void
    {
        $hseIup  = User::factory()->create(['company_id' => $this->induk->id, 'ohse_role' => 'ohse']);
        $hsePjp  = User::factory()->create(['company_id' => $this->pjp->id, 'ohse_role' => 'ohse']);

        $this->assertContains('Master Unit', $this->label($this->admin(), '/miners/dasbor'));
        $this->assertContains('Master Unit', $this->label($hseIup, '/miners/dasbor'));
        $this->assertNotContains('Master Unit', $this->label($hsePjp, '/miners/dasbor'));
    }

    public function test_syarat_nama_gerbang_mengikuti_gate(): void
    {
        $this->assertContains('Audit Baru', $this->label($this->auditor(), '/smkp'));
        $this->assertNotContains('Audit Baru', $this->label($this->auditi(), '/smkp'));
    }
}
