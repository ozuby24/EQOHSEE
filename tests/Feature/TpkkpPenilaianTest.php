<?php

namespace Tests\Feature;

use App\Models\{TpkkpAssessment, User};
use App\Support\Tpkkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * PTPKKP — halaman penilaian (Inertia).
 *
 * Halaman pertama yang dipindah dari Blade ke Vue. Ujinya berbeda
 * bentuknya dari halaman Blade lain: yang diperiksa bukan HTML yang
 * terkirim melainkan prop yang dikirim ke komponen, sebab HTML-nya baru
 * terbentuk di peramban. `assertSee` di sini akan lulus untuk alasan yang
 * salah — teksnya memang tidak ada di badan tanggapan.
 */
class TpkkpPenilaianTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['is_admin' => true]);
        $this->actingAs($u);

        return $u;
    }

    /* ══════════════ prop halaman ══════════════ */

    public function test_halaman_dirender_inertia_bukan_blade(): void
    {
        $this->admin();

        $this->get('/tpkkp/penilaian')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Tpkkp/Penilaian'));
    }

    public function test_prop_halaman_lengkap(): void
    {
        $this->admin();

        $this->get('/tpkkp/penilaian')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Tpkkp/Penilaian')
                ->has('judul')
                ->has('subjudul')
                ->has('tahun')
                ->has('metode')
                ->has('metodeAktif')
                ->has('parameter')
                ->has('entitas')
                ->has('items')
                ->has('ambang')
                ->where('bisaSunting', true)
            );
    }

    public function test_tiap_item_membawa_sel_nilai_dan_rubriknya(): void
    {
        $this->admin();

        $this->get('/tpkkp/penilaian')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('items.0', fn (AssertableInertia $i) => $i
                    ->has('kode')->has('nama')->has('metode')->has('maks')
                    ->has('nilai')->has('ket')->has('rubrik')->has('target')
                )
            );
    }

    /* ══════════════ ambang kategori ══════════════ */

    /**
     * Ambang kategori harus datang dari server, bukan ditulis di klien.
     *
     * Berkas Vue sempat memuat salinan yang ditulis tangan, dan isinya
     * keliru seluruhnya — batas maupun nama tingkatnya tidak ada di acuan.
     * Cacat seperti itu tidak menimbulkan galat: lencananya hanya menyebut
     * tingkat kematangan yang salah, dan justru itu yang dibaca orang.
     */
    public function test_ambang_kategori_dikirim_persis_seperti_acuan(): void
    {
        $this->admin();

        $ambang = $this->get('/tpkkp/penilaian')->assertOk()
                       ->viewData('page')['props']['ambang'];

        $acuan = Tpkkp::ref()['thresholds'];

        $this->assertCount(count($acuan), $ambang);

        foreach ($acuan as $i => $t) {
            $this->assertEqualsWithDelta((float) $t['lt'], $ambang[$i]['batas'], 1e-9);
            $this->assertSame($t['label'], $ambang[$i]['label']);
        }
    }

    public function test_tidak_ada_ambang_yang_ditulis_ulang_di_berkas_vue(): void
    {
        $vue = file_get_contents(resource_path('js/Pages/Tpkkp/Penilaian.vue'));

        // Nama tingkat kematangan tidak boleh muncul sebagai teks di klien;
        // kalau muncul, berarti ada salinan daftar ambang di sana lagi.
        foreach (Tpkkp::LV as $tingkat) {
            $this->assertStringNotContainsString("'{$tingkat}'", $vue,
                "Tingkat '{$tingkat}' tertulis di berkas Vue — ambang harus datang dari server.");
        }
    }

    /* ══════════════ penyaring ══════════════ */

    public function test_metode_dan_parameter_dapat_dipilih(): void
    {
        $this->admin();

        $metode = array_key_first(Tpkkp::methods());

        $this->get('/tpkkp/penilaian?m='.$metode)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('metodeAktif', $metode));
    }

    public function test_metode_tak_dikenal_jatuh_ke_metode_pertama(): void
    {
        $this->admin();

        $this->get('/tpkkp/penilaian?m=TIDAK-ADA')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) =>
                $p->where('metodeAktif', array_key_first(Tpkkp::methods())));
    }

    public function test_seluruh_item_yang_dikirim_memakai_metode_aktif(): void
    {
        // Item yang tidak memakai metode ini akan tampil dengan sel nilai
        // yang tidak berarti apa-apa bagi penilainya.
        $this->admin();

        $props = $this->get('/tpkkp/penilaian')->assertOk()->viewData('page')['props'];

        $this->assertNotEmpty($props['items']);
        foreach ($props['items'] as $it) {
            $this->assertContains($props['metodeAktif'], $it['metode'],
                "Item {$it['kode']} tidak memakai metode {$props['metodeAktif']}.");
        }
    }

    /* ══════════════ penyimpanan ══════════════ */

    public function test_nilai_tersimpan_lewat_bentuk_kiriman_yang_sama(): void
    {
        // Bentuk kiriman sengaja tidak diubah saat halaman dipindah ke Vue,
        // supaya saveAssess() tetap melayani kedua tampilan.
        $this->admin();

        $props = $this->get('/tpkkp/penilaian')->assertOk()->viewData('page')['props'];
        $item  = $props['items'][0];
        $sel   = array_key_first($item['nilai']);

        $this->post('/tpkkp/penilaian?tahun='.$props['tahun'], [
            'metode' => $props['metodeAktif'],
            'n'      => [$item['kode'] => [$sel => 4]],
            'ket'    => [$item['kode'] => 'Bukti dokumen terlampir.'],
        ])->assertRedirect();

        $a = TpkkpAssessment::forYear($props['tahun']);
        $this->assertSame(4, $sel === '_'
            ? $a->cell($props['metodeAktif'], $item['kode'])
            : $a->cell($props['metodeAktif'], $item['kode'], $sel));
        $this->assertSame('Bukti dokumen terlampir.', $a->ket($props['metodeAktif'], $item['kode']));
    }

    public function test_nilai_tersimpan_terbaca_kembali_pada_prop(): void
    {
        $this->admin();

        $props = $this->get('/tpkkp/penilaian')->assertOk()->viewData('page')['props'];
        $item  = $props['items'][0];
        $sel   = array_key_first($item['nilai']);

        $this->post('/tpkkp/penilaian?tahun='.$props['tahun'], [
            'metode' => $props['metodeAktif'],
            'n'      => [$item['kode'] => [$sel => 3]],
        ]);

        $lagi = $this->get('/tpkkp/penilaian')->assertOk()->viewData('page')['props'];
        $this->assertSame(3, collect($lagi['items'])->firstWhere('kode', $item['kode'])['nilai'][$sel]);
    }

    public function test_pengguna_biasa_tidak_dapat_menyimpan(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->post('/tpkkp/penilaian', ['metode' => array_key_first(Tpkkp::methods())])
             ->assertForbidden();
    }

    public function test_pengguna_biasa_membuka_halaman_tanpa_izin_sunting(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/tpkkp/penilaian')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->where('bisaSunting', false));
    }

    public function test_tamu_tidak_dapat_membuka_halaman(): void
    {
        $this->get('/tpkkp/penilaian')->assertRedirect(route('login'));
    }

    /* ══════════════ data bersama ══════════════ */

    public function test_menu_bersama_sama_isinya_dengan_bilah_samping_blade(): void
    {
        // Keduanya membaca App\Support\Menu; kalau salah satu menyusun
        // sendiri, menunya akan berbeda antara halaman Blade dan Vue tanpa
        // ada galat yang muncul.
        $u = $this->admin();

        $props = $this->get('/tpkkp/penilaian')->assertOk()->viewData('page')['props'];

        $this->assertSame(
            count(\App\Support\Menu::untuk($u)),
            count($props['menu']['modul']),
            'Jumlah modul pada menu Inertia harus sama dengan katalog menu.'
        );

        $aktif = collect($props['menu']['modul'])->firstWhere('aktif', true);
        $this->assertSame('tpkkp', $aktif['kunci']);
    }

    public function test_data_bersama_membawa_pengguna_dan_tema(): void
    {
        $this->admin();

        $this->get('/tpkkp/penilaian')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->has('pengguna.nama')
                ->has('warna.aksen')
                ->has('pengumuman')
            );
    }

    /* ══════════════ halaman Blade tidak terganggu ══════════════ */

    public function test_halaman_tpkkp_lain_tetap_blade(): void
    {
        // Middleware Inertia dipasang pada seluruh rute web; halaman yang
        // tidak memanggil Inertia::render() harus tetap mengirim HTML utuh.
        $this->admin();

        foreach (['/tpkkp', '/tpkkp/rekap'] as $url) {
            $this->get($url)->assertOk()->assertSee('<!DOCTYPE html>', false);
        }
    }
}
