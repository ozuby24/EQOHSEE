<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Tpkkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * PTPKKP — Mitra & Akses (Inertia).
 *
 * Roster menentukan kolom nilai di halaman Penilaian, jadi kesalahan di
 * sini tidak berhenti di halaman ini. Yang paling perlu dijaga adalah
 * aturan pembersihan barisnya — dipangkas, kosong dibuang, kembar dibuang,
 * dan kotak yang kosong kembali ke bawaan instrumen — sebab semuanya
 * bekerja tanpa pesan apa pun ke pemakai.
 */
class TpkkpRosterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['is_admin' => true]);
        $this->actingAs($u);

        return $u;
    }

    private function props(): array
    {
        return $this->get('/tpkkp/roster')->assertOk()->viewData('page')['props'];
    }

    /** Metode pertama yang memang memakai entitas. */
    private function berentitas(): array
    {
        foreach ($this->props()['metode'] as $m) {
            if ($m['punyaEntitas']) return $m;
        }

        $this->fail('Tidak ada satu pun metode berentitas.');
    }

    /* ══════════════ prop halaman ══════════════ */

    public function test_halaman_dirender_inertia_bukan_blade(): void
    {
        $this->admin();

        $this->get('/tpkkp/roster')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Tpkkp/Roster')
            ->has('judul')->has('subjudul')->has('picker')->has('tahun')
            ->has('metode')->where('bisaSunting', true));
    }

    public function test_seluruh_metode_instrumen_terkirim_termasuk_yang_tanpa_entitas(): void
    {
        // Metode tanpa entitas tetap ditampilkan dengan keterangannya.
        // Menyaringnya keluar membuat orang mengira metodenya belum diatur.
        $this->admin();

        $this->assertSame(
            array_keys(Tpkkp::methods()),
            array_column($this->props()['metode'], 'kode')
        );
    }

    public function test_metode_tanpa_entitas_dikirim_tanpa_daftar(): void
    {
        $this->admin();

        foreach ($this->props()['metode'] as $m) {
            $punya = (bool) (Tpkkp::methods()[$m['kode']]['entityLabel'] ?? '');

            $this->assertSame($punya, $m['punyaEntitas']);
            if (!$punya) $this->assertSame([], $m['entitas']);
        }
    }

    public function test_daftar_bawaan_instrumen_ikut_dikirim(): void
    {
        // Layar memakainya untuk mengatakan apa yang akan terjadi kalau
        // kotaknya dikosongkan, sebelum orang menekan simpan.
        $this->admin();

        foreach ($this->props()['metode'] as $m) {
            $this->assertSame(
                array_values(Tpkkp::methods()[$m['kode']]['entities'] ?? []),
                $m['bawaan']
            );
        }
    }

    /* ══════════════ simpan ══════════════ */

    public function test_simpan_lalu_terbaca_kembali(): void
    {
        $this->admin();
        $kode = $this->berentitas()['kode'];

        $this->post('/tpkkp/roster', ['roster' => [$kode => "PT Satu\nPT Dua\nPT Tiga"]])
            ->assertRedirect();

        foreach ($this->props()['metode'] as $m) {
            if ($m['kode'] === $kode) {
                $this->assertSame(['PT Satu', 'PT Dua', 'PT Tiga'], $m['entitas']);

                return;
            }
        }

        $this->fail("Metode {$kode} hilang dari daftar.");
    }

    public function test_baris_dipangkas_kosong_dan_kembar_dibuang(): void
    {
        $this->admin();
        $kode = $this->berentitas()['kode'];

        $this->post('/tpkkp/roster', [
            'roster' => [$kode => "  PT Satu  \n\n\nPT Dua\n   \nPT Satu\nPT Tiga\n"],
        ])->assertRedirect();

        foreach ($this->props()['metode'] as $m) {
            if ($m['kode'] === $kode) {
                $this->assertSame(['PT Satu', 'PT Dua', 'PT Tiga'], $m['entitas']);

                return;
            }
        }
    }

    public function test_kotak_kosong_kembali_ke_bawaan_instrumen(): void
    {
        $this->admin();
        $m = $this->berentitas();

        $this->post('/tpkkp/roster', ['roster' => [$m['kode'] => "PT Sendiri"]])->assertRedirect();
        $this->post('/tpkkp/roster', ['roster' => [$m['kode'] => "   \n\n  "]])->assertRedirect();

        foreach ($this->props()['metode'] as $n) {
            if ($n['kode'] === $m['kode']) {
                $this->assertSame($m['bawaan'], $n['entitas'],
                    'Kotak kosong tidak kembali ke daftar bawaan instrumen.');

                return;
            }
        }
    }

    public function test_metode_tanpa_entitas_tidak_bisa_diisi_lewat_kiriman(): void
    {
        // Mengisinya akan memunculkan kolom nilai di halaman Penilaian
        // untuk metode yang memang tidak dinilai per entitas.
        $this->admin();

        $tanpa = null;
        foreach ($this->props()['metode'] as $m) {
            if (!$m['punyaEntitas']) { $tanpa = $m['kode']; break; }
        }

        if ($tanpa === null) $this->markTestSkipped('Seluruh metode memakai entitas.');

        $this->post('/tpkkp/roster', ['roster' => [$tanpa => "PT Selundupan"]])->assertRedirect();

        foreach ($this->props()['metode'] as $m) {
            if ($m['kode'] === $tanpa) {
                $this->assertSame([], $m['entitas']);

                return;
            }
        }
    }

    /* ══════════════ hak akses ══════════════ */

    public function test_bukan_admin_tidak_dapat_menyimpan(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/tpkkp/roster')->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->where('bisaSunting', false)
        );

        $this->post('/tpkkp/roster', ['roster' => []])->assertForbidden();
    }

    public function test_tamu_tidak_dapat_membuka(): void
    {
        $this->get('/tpkkp/roster')->assertRedirect(route('login'));
    }
}
