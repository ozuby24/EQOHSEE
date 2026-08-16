<?php

namespace Tests\Feature;

use App\Models\{Company, MineOperationalRecord, User};
use App\Support\DataContoh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pemuat data contoh dari Pusat Kendali.
 *
 * Rutenya membuang seluruh data satu perusahaan. Yang diuji di sini
 * adalah lapis-lapis yang memisahkan tombol itu dari kehilangan data
 * sungguhan — dan setiap lapisnya diuji dari sisi rutenya, sebab
 * penjagaan yang hanya ada di dalam kelas pemuatnya tetap dapat
 * dilewati oleh rute yang lupa memanggilnya.
 */
class PusatKendaliDataContohTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Company $sungguhan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->sungguhan = Company::create(['name' => 'PT Sungguhan']);
    }

    private function contoh(): Company
    {
        $c = Company::create(['name' => 'PT Contoh', 'demo' => true]);
        User::factory()->create(['company_id' => $c->id]);

        return $c;
    }

    private function satuBaris(Company $c): MineOperationalRecord
    {
        return MineOperationalRecord::withoutGlobalScopes()->create([
            'company_id' => $c->id, 'tanggal' => '2026-01-05',
            'shift' => 'siang', 'produksi_ton' => 1000,
        ]);
    }

    /* ---------- hak akses ---------- */

    public function test_bukan_admin_tidak_dapat_memuat(): void
    {
        $c = $this->contoh();
        $biasa = User::factory()->create();

        $this->actingAs($biasa)
            ->post(route('admin.system.demo.muat', $c))
            ->assertForbidden();

        $this->assertSame(0, DataContoh::hitungIsi($c->fresh()));
    }

    public function test_bukan_admin_tidak_dapat_menandai(): void
    {
        $biasa = User::factory()->create();

        $this->actingAs($biasa)
            ->post(route('admin.system.demo.tandai', $this->sungguhan), ['demo' => true])
            ->assertForbidden();

        $this->assertFalse((bool) $this->sungguhan->fresh()->demo);
    }

    public function test_tamu_dialihkan_ke_halaman_masuk(): void
    {
        $this->post(route('admin.system.demo.muat', $this->contoh()))
            ->assertRedirect(route('login'));
    }

    /* ---------- penjaga perusahaan contoh ---------- */

    public function test_perusahaan_biasa_tidak_dapat_dimuati(): void
    {
        $baris = $this->satuBaris($this->sungguhan);

        $this->actingAs($this->admin)
            ->post(route('admin.system.demo.muat', $this->sungguhan))
            ->assertRedirect()
            ->assertSessionHasErrors('demo');

        $this->assertNotNull(MineOperationalRecord::withoutGlobalScopes()->find($baris->id));
    }

    /**
     * Menandai perusahaan yang sudah berisi berarti membuka seluruh
     * datanya bagi tombol yang membuangnya. Itu boleh dilakukan — tetapi
     * harus dikatakan, bukan diklik.
     */
    public function test_perusahaan_berisi_tidak_dapat_ditandai_tanpa_menyebut_namanya(): void
    {
        $this->satuBaris($this->sungguhan);

        $this->actingAs($this->admin)
            ->post(route('admin.system.demo.tandai', $this->sungguhan), ['demo' => true])
            ->assertSessionHasErrors('demo');

        $this->assertFalse((bool) $this->sungguhan->fresh()->demo);
    }

    /**
     * Nama yang benar-benar salah tetap ditolak.
     *
     * Dahulu uji ini memakai 'pt sungguhan' — yang berbeda dari
     * 'PT Sungguhan' HANYA pada huruf besar-kecilnya. Itu bukan salah
     * ketik melainkan ejaan yang sama, dan menolaknya membuat
     * penjagaan ini gagal pada orang yang mengetik persis apa yang
     * terbaca di layar. Terjadi sungguhan pada perusahaan bernama
     * "DEMO": pemiliknya tidak dapat menandainya sama sekali.
     *
     * Sekarang yang diuji salah ketik yang sesungguhnya — satu huruf
     * hilang.
     */
    public function test_nama_yang_salah_ketik_tetap_ditolak(): void
    {
        $this->satuBaris($this->sungguhan);

        $this->actingAs($this->admin)
            ->post(route('admin.system.demo.tandai', $this->sungguhan),
                   ['demo' => true, 'sadar' => 'PT Sungguhn'])
            ->assertSessionHasErrors('demo');

        $this->assertFalse((bool) $this->sungguhan->fresh()->demo);
    }

    /**
     * Huruf besar-kecil dan spasi di ujung TIDAK diperhitungkan.
     *
     * Yang diminta konfirmasi ini adalah kesengajaan, bukan ketepatan
     * mengetik. Keduanya tidak terlihat di layar, sehingga penolakan
     * karenanya terbaca sebagai kerusakan — bukan sebagai penjagaan.
     */
    public function test_beda_huruf_besar_dan_spasi_tetap_diterima(): void
    {
        $this->satuBaris($this->sungguhan);

        $this->actingAs($this->admin)
            ->post(route('admin.system.demo.tandai', $this->sungguhan),
                   ['demo' => true, 'sadar' => '  '.strtolower($this->sungguhan->name).' '])
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) $this->sungguhan->fresh()->demo);
    }

    public function test_nama_yang_tepat_membuka_penandaannya(): void
    {
        $this->satuBaris($this->sungguhan);

        $this->actingAs($this->admin)
            ->post(route('admin.system.demo.tandai', $this->sungguhan),
                   ['demo' => true, 'sadar' => 'PT Sungguhan'])
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) $this->sungguhan->fresh()->demo);
    }

    public function test_perusahaan_kosong_ditandai_tanpa_penegasan(): void
    {
        $kosong = Company::create(['name' => 'PT Kosong']);

        $this->actingAs($this->admin)
            ->post(route('admin.system.demo.tandai', $kosong), ['demo' => true])
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) $kosong->fresh()->demo);
    }

    /**
     * Melepas tanda tidak menuntut penegasan apa pun: arah itu
     * menyempitkan apa yang dapat dibuang, bukan melebarkannya.
     */
    public function test_melepas_tanda_tidak_menuntut_penegasan(): void
    {
        $c = $this->contoh();

        $this->actingAs($this->admin)
            ->post(route('admin.system.demo.tandai', $c), ['demo' => false])
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) $c->fresh()->demo);
    }

    /* ---------- pemuatan ---------- */

    public function test_pemuatan_mengisi_seluruh_modul(): void
    {
        $c = $this->contoh();

        $this->actingAs($this->admin)
            ->post(route('admin.system.demo.muat', $c))
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('ok');

        $this->assertGreaterThan(0, MineOperationalRecord::withoutGlobalScopes()
            ->where('company_id', $c->id)->count());
    }

    public function test_pemuatan_tidak_menyentuh_perusahaan_lain(): void
    {
        $c = $this->contoh();
        $baris = $this->satuBaris($this->sungguhan);

        $this->actingAs($this->admin)->post(route('admin.system.demo.muat', $c));
        $this->actingAs($this->admin)->post(route('admin.system.demo.muat', $c));

        $this->assertNotNull(MineOperationalRecord::withoutGlobalScopes()->find($baris->id));
    }

    /**
     * Administrator biasanya bukan orang perusahaan yang dimuati.
     * Barisnya tetap harus lahir milik perusahaan sasaran, bukan milik
     * perusahaan administratornya.
     */
    public function test_baris_milik_perusahaan_sasaran_bukan_milik_administratornya(): void
    {
        $c = $this->contoh();
        $adminBerperusahaan = User::factory()->create([
            'is_admin' => true, 'company_id' => $this->sungguhan->id,
        ]);

        $this->actingAs($adminBerperusahaan)->post(route('admin.system.demo.muat', $c));

        $this->assertSame(0, MineOperationalRecord::withoutGlobalScopes()
            ->where('company_id', $this->sungguhan->id)->count());
        $this->assertGreaterThan(0, MineOperationalRecord::withoutGlobalScopes()
            ->where('company_id', $c->id)->count());
    }

    /* ---------- tampilan ---------- */

    public function test_halaman_menyebut_isi_perusahaan_contoh(): void
    {
        $c = $this->contoh();
        $this->actingAs($this->admin)->post(route('admin.system.demo.muat', $c));

        $this->actingAs($this->admin)
            ->get(route('admin.system'))
            ->assertInertia(function ($p) use ($c) {
                $baris = collect($p->toArray()['props']['perusahaan'])
                    ->firstWhere('id', $c->id);

                $this->assertTrue($baris['demo']);
                $this->assertNotEmpty($baris['isi']);
                $this->assertArrayHasKey('mine_operational_records', $baris['isi']);

                $lain = collect($p->toArray()['props']['perusahaan'])
                    ->firstWhere('id', $this->sungguhan->id);

                $this->assertFalse($lain['demo']);
                $this->assertNull($lain['isi']);
            });
    }
}
