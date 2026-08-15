<?php

namespace Tests\Feature;

use App\Models\{TpkkpAssessment, User};
use App\Support\Tpkkp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * PTPKKP — Profil (Inertia).
 *
 * Halaman berformulir pertama di PTPKKP yang dipindah ke Vue. Yang paling
 * mudah rusak diam-diam di sini bukan tampilannya melainkan bentuk kiriman
 * POST-nya: kolomnya tidak berubah, hanya berpindah pengirim, dan kiriman
 * yang salah bentuk tetap menghasilkan tanggapan 302 yang terlihat sehat
 * sementara isinya tidak tersimpan. Karena itu ujinya menyimpan sungguhan
 * lalu membaca ulang lewat prop halaman, bukan lewat model.
 */
class TpkkpProfilTest extends TestCase
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

        $this->get('/tpkkp/profil')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Tpkkp/Profil')
                ->has('judul')
                ->has('subjudul')
                ->has('picker')
                ->has('tahun')
                ->has('roster')
                ->where('bisaSunting', true));
    }

    public function test_isian_membawa_seluruh_medan_formulir(): void
    {
        // Vue mengikat v-model ke tiap kunci; kunci yang hilang membuat
        // medannya undefined dan nilai lamanya terhapus saat disimpan.
        $this->admin();

        $this->get('/tpkkp/profil')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->has('isian', fn (AssertableInertia $i) => $i
                ->has('judul')->has('organisasi')->has('site')
                ->has('komoditas')->has('ktt')->has('basis')->has('status')));
    }

    public function test_isian_tidak_pernah_null(): void
    {
        // v-model pada null membuat input tak terkendali; server
        // memulangkan string kosong supaya keadaannya satu macam saja.
        $this->admin();

        $isian = $this->get('/tpkkp/profil')->assertOk()->viewData('page')['props']['isian'];

        foreach ($isian as $kunci => $nilai) {
            $this->assertIsString($nilai, "isian.{$kunci} bukan string.");
        }
    }

    /* ══════════════ roster ══════════════ */

    public function test_roster_hanya_memuat_metode_yang_punya_entitas(): void
    {
        // Metode tanpa entitas dinilai satu angka untuk seluruh organisasi.
        // Judul kosong tanpa isi di bawahnya membuat halaman tampak rusak.
        $this->admin();

        $props = $this->get('/tpkkp/profil')->assertOk()->viewData('page')['props'];

        foreach ($props['roster'] as $r) {
            $this->assertNotEmpty($r['entitas'], "Metode {$r['kode']} masuk roster tanpa entitas.");
            $this->assertArrayHasKey($r['kode'], Tpkkp::methods(),
                "Kode metode {$r['kode']} tidak dikenal instrumen.");
        }
    }

    /* ══════════════ simpan ══════════════ */

    public function test_simpan_lalu_terbaca_kembali_di_prop_halaman(): void
    {
        $this->admin();

        $kirim = [
            'judul'      => 'Penilaian TPKKP Uji',
            'organisasi' => 'PT Tambang Uji',
            'site'       => 'Site Utara',
            'komoditas'  => 'Batubara',
            'ktt'        => 'Ir. Uji Coba',
            'basis'      => 'Kepdirjen 185.K/2019',
            'status'     => 'aktif',
        ];

        $this->post('/tpkkp/profil', $kirim)->assertRedirect();

        $isian = $this->get('/tpkkp/profil')->assertOk()->viewData('page')['props']['isian'];

        foreach ($kirim as $kunci => $nilai) {
            $this->assertSame($nilai, $isian[$kunci], "Medan {$kunci} tidak tersimpan utuh.");
        }
    }

    public function test_simpan_tidak_menghapus_kunci_profil_lain(): void
    {
        // profil disimpan sebagai satu larik; menimpanya bulat-bulat akan
        // membuang kunci yang tidak ada di formulir ini.
        $this->admin();

        // Berkas penilaiannya dibuat saat halaman pertama dibuka.
        $this->get('/tpkkp/profil')->assertOk();

        $a = TpkkpAssessment::firstOrFail();
        $a->profil = array_merge($a->profil ?? [], ['catatan_internal' => 'jangan hilang']);
        $a->save();

        $this->post('/tpkkp/profil', ['organisasi' => 'PT Lain'])->assertRedirect();

        $this->assertSame('jangan hilang', $a->fresh()->profil['catatan_internal'] ?? null);
    }

    public function test_status_di_luar_daftar_ditolak(): void
    {
        $this->admin();

        $this->post('/tpkkp/profil', ['status' => 'entahlah'])
            ->assertSessionHasErrors('status');
    }

    /* ══════════════ hak akses ══════════════ */

    public function test_bukan_admin_tidak_dapat_menyunting(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/tpkkp/profil')->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->where('bisaSunting', false)
        );
    }

    public function test_tamu_tidak_dapat_membuka(): void
    {
        $this->get('/tpkkp/profil')->assertRedirect(route('login'));
    }
}
