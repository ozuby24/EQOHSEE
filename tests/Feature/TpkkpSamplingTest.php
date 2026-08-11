<?php

namespace Tests\Feature;

use App\Http\Controllers\TpkkpController;
use App\Models\{TpkkpAssessment, User};
use App\Support\Tpkkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * PTPKKP — Kalkulator Slovin (Inertia).
 *
 * Halaman ini menghitung ulang sambil orang mengetik. Rumusnya sengaja
 * tidak disalin ke peramban: pratinjaunya memanggil halaman yang sama
 * dengan angka lewat kueri, sehingga yang tampak saat mengetik dan yang
 * tersimpan kemudian mustahil berbeda. Uji di bawah menjaga kedua jalur
 * itu tetap menghasilkan angka yang sama, dan menjaga pratinjau benar-
 * benar tidak menyimpan apa pun.
 */
class TpkkpSamplingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['is_admin' => true]);
        $this->actingAs($u);

        return $u;
    }

    private function props(array $kueri = []): array
    {
        return $this->get('/tpkkp/sampling' . ($kueri ? '?' . http_build_query($kueri) : ''))
            ->assertOk()->viewData('page')['props'];
    }

    /* ══════════════ prop halaman ══════════════ */

    public function test_halaman_dirender_inertia_bukan_blade(): void
    {
        $this->admin();

        $this->get('/tpkkp/sampling')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Tpkkp/Sampling')
            ->has('judul')->has('subjudul')->has('picker')->has('tahun')
            ->has('strata')->has('e')->has('eMin')->has('eMaks')
            ->has('alokasi.N')->has('alokasi.n')->has('alokasi.total')->has('alokasi.baris')
            ->where('bisaSunting', true));
    }

    public function test_batas_margin_galat_sama_dengan_yang_dipakai_validasi(): void
    {
        // Atribut min/max pada input diambil dari prop ini. Kalau berbeda
        // dari aturan validasi, peramban mengizinkan angka yang lalu
        // ditolak server — atau sebaliknya, melarang angka yang sah.
        $this->admin();

        $p = $this->props();

        $this->assertSame(TpkkpController::E_MIN, $p['eMin']);
        $this->assertSame(TpkkpController::E_MAKS, $p['eMaks']);

        $this->post('/tpkkp/sampling', ['e' => TpkkpController::E_MIN])
            ->assertSessionHasNoErrors();
        $this->post('/tpkkp/sampling', ['e' => TpkkpController::E_MAKS])
            ->assertSessionHasNoErrors();
        $this->post('/tpkkp/sampling', ['e' => TpkkpController::E_MAKS + 0.01])
            ->assertSessionHasErrors('e');
    }

    public function test_setiap_strata_punya_baris_alokasi(): void
    {
        $this->admin();

        $p = $this->props();

        $this->assertSame(
            array_column($p['strata'], 'nama'),
            array_column($p['alokasi']['baris'], 'nama')
        );
    }

    /* ══════════════ pratinjau ══════════════ */

    public function test_pratinjau_memakai_angka_kueri_tanpa_menyimpannya(): void
    {
        $this->admin();

        $awal  = $this->props();
        $nama  = $awal['strata'][0]['nama'];
        $lama  = $awal['strata'][0]['N'];

        $lihat = $this->props(['N' => [$nama => $lama + 500], 'e' => 0.1]);

        $this->assertSame($lama + 500, $lihat['strata'][0]['N']);
        $this->assertSame(0.1, $lihat['e']);

        // Tanpa kueri, angkanya kembali seperti semula.
        $this->assertSame($lama, $this->props()['strata'][0]['N']);
        $this->assertSame($awal['e'], $this->props()['e']);
    }

    public function test_pratinjau_dan_hasil_tersimpan_menghasilkan_angka_sama(): void
    {
        // Inti halaman ini. Kalau keduanya bisa berbeda, tidak ada galat
        // yang muncul — hanya laporan yang salah beberapa orang.
        $this->admin();

        $strata = $this->props()['strata'];
        $kirim  = [];
        foreach ($strata as $i => $s) $kirim[$s['nama']] = 120 + $i * 37;

        $lihat = $this->props(['N' => $kirim, 'e' => 0.07])['alokasi'];

        $this->post('/tpkkp/sampling', ['N' => $kirim, 'e' => 0.07])->assertRedirect();

        $this->assertSame($lihat, $this->props()['alokasi']);
    }

    public function test_pratinjau_mengabaikan_strata_yang_tidak_dikenal(): void
    {
        $this->admin();

        $jumlah = count($this->props()['strata']);

        $p = $this->props(['N' => ['Strata Karangan' => 900]]);

        $this->assertCount($jumlah, $p['strata']);
        $this->assertNotContains('Strata Karangan', array_column($p['strata'], 'nama'));
    }

    public function test_pratinjau_menjepit_margin_galat_ke_rentang_yang_sah(): void
    {
        // Tanpa penjepitan, e = 0 membuat pembagian bergantung pada N saja
        // dan e negatif menghasilkan angka yang tidak berarti apa-apa.
        $this->admin();

        $this->assertSame(TpkkpController::E_MAKS, $this->props(['e' => 9])['e']);
        $this->assertSame(TpkkpController::E_MIN,  $this->props(['e' => -3])['e']);
    }

    /* ══════════════ simpan ══════════════ */

    public function test_simpan_lalu_terbaca_kembali(): void
    {
        $this->admin();

        $nama = $this->props()['strata'][0]['nama'];

        $this->post('/tpkkp/sampling', ['N' => [$nama => 777], 'e' => 0.03])->assertRedirect();

        $p = $this->props();

        $this->assertSame(777, $p['strata'][0]['N']);
        $this->assertSame(0.03, $p['e']);
    }

    public function test_simpan_menolak_strata_yang_tidak_dikenal(): void
    {
        // Kunci sembarang akan menjadi baris strata permanen yang tidak
        // pernah diminta siapa pun, tanpa cara menghapusnya dari layar.
        $this->admin();

        $jumlah = count($this->props()['strata']);

        $this->post('/tpkkp/sampling', ['N' => ['Strata Karangan' => 900], 'e' => 0.05])
            ->assertRedirect();

        $this->assertCount($jumlah, $this->props()['strata']);
    }

    /* ══════════════ hitungan ══════════════ */

    public function test_alokasi_sama_dengan_perhitungan_acuan(): void
    {
        $this->admin();

        $p = $this->props();

        $acuan = Tpkkp::strataAlloc(
            array_map(fn ($s) => ['j' => $s['nama'], 'N' => $s['N']], $p['strata']),
            $p['e']
        );

        $this->assertSame($acuan['N'], $p['alokasi']['N']);
        $this->assertSame($acuan['n'], $p['alokasi']['n']);
        $this->assertSame($acuan['total'], $p['alokasi']['total']);
    }

    /* ══════════════ hak akses ══════════════ */

    public function test_bukan_admin_tidak_dapat_menyimpan(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/tpkkp/sampling')->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->where('bisaSunting', false)
        );

        $this->post('/tpkkp/sampling', ['e' => 0.05])->assertForbidden();
    }

    public function test_tamu_tidak_dapat_membuka(): void
    {
        $this->get('/tpkkp/sampling')->assertRedirect(route('login'));
    }
}
