<?php

namespace Tests\Feature;

use App\Http\Controllers\KuesionerController;
use App\Models\{Company, TpkkpResponse, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Kuesioner PTPKKP — sisi admin (Inertia).
 *
 * Halaman ini menerbitkan tautan publik bebas akses, jadi yang paling
 * perlu dijaga adalah batas siapa melihat perusahaan mana: pemakai biasa
 * terikat pada perusahaannya sendiri dan tidak boleh berpindah lewat
 * ?company=, sebab tautan yang terlihat di sana bisa langsung disebar.
 */
class TpkkpKuesionerAdminTest extends TestCase
{
    use RefreshDatabase;

    private function perusahaan(string $nama): Company
    {
        return Company::create(['name' => $nama]);
    }

    private function props(string $kueri = ''): array
    {
        return $this->get('/tpkkp/kuesioner' . $kueri)->assertOk()->viewData('page')['props'];
    }

    /* ══════════════ tanpa perusahaan ══════════════ */

    /**
     * Tanpa satu pun perusahaan, halamannya tetap terbuka.
     *
     * Dulu ini abort 404. Menunya selalu tampak di bilah samping, jadi
     * pemasangan baru berujung pada halaman galat tanpa satu pun petunjuk
     * tentang apa yang sebenarnya kurang.
     */
    public function test_tanpa_perusahaan_halaman_tetap_terbuka_dengan_keadaan_kosong(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get('/tpkkp/kuesioner')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Tpkkp/Kuesioner')
            ->where('perusahaan', null)
            ->where('urlPublik', null)
            ->where('ringkas', [])
            ->where('responden', []));
    }

    /* ══════════════ prop halaman ══════════════ */

    public function test_halaman_dirender_inertia_bukan_blade(): void
    {
        $this->perusahaan('PT Satu');
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get('/tpkkp/kuesioner')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Tpkkp/Kuesioner')
            ->has('judul')->has('subjudul')->has('picker')
            ->has('perusahaan.id')->has('perusahaan.nama')
            ->has('daftarPerusahaan')->has('urlPublik')
            ->has('ringkas')->has('responden')
            ->where('bisaTarik', true));
    }

    public function test_ringkas_memuat_seluruh_kategori_beserta_tautan_langsungnya(): void
    {
        $this->perusahaan('PT Satu');
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $ringkas = $this->props()['ringkas'];

        $this->assertSame(array_keys(KuesionerController::KATEGORI), array_column($ringkas, 'kunci'));

        // Tautan langsung harus benar-benar membuka formulir kategorinya,
        // tanpa login — itulah gunanya disebar.
        $this->post('/logout');

        foreach ($ringkas as $r) {
            $this->assertIsArray($r['params']);
            $this->get($r['url'])->assertOk();
        }
    }

    public function test_tautan_publik_memakai_token_perusahaan(): void
    {
        $c = $this->perusahaan('PT Satu');
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $url = $this->props()['urlPublik'];

        // Tautannya harus benar-benar bisa dibuka tanpa login.
        $this->post('/logout');
        $this->get($url)->assertOk();
    }

    public function test_ganti_tautan_membuat_tautan_lama_tidak_berlaku(): void
    {
        $c = $this->perusahaan('PT Satu');
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $lama = $this->props()['urlPublik'];

        $this->post('/kuesioner/token', ['company_id' => $c->id])->assertRedirect();

        $baru = $this->props()['urlPublik'];

        $this->assertNotSame($lama, $baru);

        $this->post('/logout');
        $this->get($lama)->assertNotFound();
        $this->get($baru)->assertOk();
    }

    /* ══════════════ responden ══════════════ */

    public function test_responden_hanya_dari_perusahaan_yang_sedang_dibuka(): void
    {
        $a = $this->perusahaan('PT A');
        $b = $this->perusahaan('PT B');

        TpkkpResponse::create(['company_id' => $a->id, 'cat' => 'pekerja',
                               'nrp' => 'A-1', 'answers' => ['x' => 4], 'ts' => now()]);
        TpkkpResponse::create(['company_id' => $b->id, 'cat' => 'pekerja',
                               'nrp' => 'B-1', 'answers' => ['x' => 5], 'ts' => now()]);

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $nrp = array_column($this->props("?company={$a->id}")['responden'], 'nrp');

        $this->assertSame(['A-1'], $nrp);
    }

    public function test_hapus_responden(): void
    {
        $c = $this->perusahaan('PT Satu');
        $r = TpkkpResponse::create(['company_id' => $c->id, 'cat' => 'pekerja',
                                    'nrp' => 'A-1', 'answers' => ['x' => 4], 'ts' => now()]);

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->delete("/kuesioner/{$r->id}")->assertRedirect();

        $this->assertSame([], $this->props()['responden']);
    }

    /* ══════════════ batas perusahaan ══════════════ */

    public function test_pemakai_biasa_terkunci_pada_perusahaannya_sendiri(): void
    {
        // Tautan yang terlihat di halaman ini bisa langsung disebar, jadi
        // ?company= tidak boleh membukanya untuk perusahaan orang lain.
        $milik = $this->perusahaan('PT Milik');
        $lain  = $this->perusahaan('PT Lain');

        $this->actingAs(User::factory()->create(['is_admin' => false, 'company_id' => $milik->id]));

        $p = $this->props("?company={$lain->id}");

        $this->assertSame($milik->id, $p['perusahaan']['id']);
        $this->assertSame([$milik->id], array_column($p['daftarPerusahaan'], 'id'));
        $this->assertFalse($p['bisaTarik']);
    }

    public function test_admin_dapat_berpindah_perusahaan(): void
    {
        $a = $this->perusahaan('PT A');
        $b = $this->perusahaan('PT B');

        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->assertSame($b->id, $this->props("?company={$b->id}")['perusahaan']['id']);
        $this->assertCount(2, $this->props()['daftarPerusahaan']);
    }

    public function test_tamu_tidak_dapat_membuka(): void
    {
        $this->get('/tpkkp/kuesioner')->assertRedirect(route('login'));
    }
}
