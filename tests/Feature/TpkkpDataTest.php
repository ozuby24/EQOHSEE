<?php

namespace Tests\Feature;

use App\Http\Controllers\TpkkpLanjutController as Lanjut;
use App\Models\{TpkkpAssessment, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * PTPKKP — Data & Koneksi (Inertia).
 *
 * Dua tindakan di halaman ini menimpa data dan tidak bisa dibatalkan dari
 * layar. Yang paling perlu dijaga bukan tampilannya melainkan lingkaran
 * ekspor–impor: berkas yang keluar harus bisa masuk kembali utuh, dan
 * daftar kunci yang dijanjikan layar harus benar-benar kunci yang dipakai.
 */
class TpkkpDataTest extends TestCase
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
        return $this->get('/tpkkp/data')->assertOk()->viewData('page')['props'];
    }

    /* ══════════════ prop halaman ══════════════ */

    public function test_halaman_dirender_inertia_bukan_blade(): void
    {
        $this->admin();

        $this->get('/tpkkp/data')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Tpkkp/Data')
            ->has('judul')->has('subjudul')->has('picker')->has('tahun')
            ->has('ringkas')->has('urlEkspor')->has('kunciDikenal')
            ->where('bisaSunting', true));
    }

    /**
     * Kunci yang dijanjikan layar sama dengan yang dipakai impor.
     *
     * Layar menyebut kunci apa saja yang akan terpakai sebelum tombolnya
     * ditekan. Kalau daftarnya beda dari yang dibaca server, layar
     * menjanjikan sesuatu yang ternyata dibuang tanpa keterangan apa pun.
     */
    public function test_kunci_yang_dijanjikan_sama_dengan_yang_dibaca_impor(): void
    {
        $this->admin();

        $this->assertSame(
            array_merge(Lanjut::KUNCI_LARIK, Lanjut::KUNCI_TEKS),
            $this->props()['kunciDikenal']
        );
    }

    /* ══════════════ lingkaran ekspor–impor ══════════════ */

    public function test_berkas_ekspor_memuat_seluruh_kunci_yang_dikenali_impor(): void
    {
        $this->admin();

        $isi = $this->get('/tpkkp/data/ekspor')->assertOk()->json();

        foreach ($this->props()['kunciDikenal'] as $k) {
            $this->assertArrayHasKey($k, $isi, "Kunci {$k} tidak ikut diekspor.");
        }
    }

    public function test_hasil_ekspor_dapat_diimpor_kembali_utuh(): void
    {
        $this->admin();

        $this->post('/tpkkp/profil', ['judul' => 'Sebelum ekspor', 'organisasi' => 'PT Uji']);
        $this->post('/tpkkp/program', ['param' => 'X.1', 'opsi' => 'Program penanda']);

        $berkas = $this->get('/tpkkp/data/ekspor')->assertOk()->json();

        // Rusak dulu isinya, lalu pulihkan dari berkas.
        $a = TpkkpAssessment::firstOrFail();
        $a->judul = 'Tertimpa';
        $a->programs = [];
        $a->profil = [];
        $a->save();

        $this->post('/tpkkp/data/impor', ['json' => json_encode($berkas)])->assertRedirect();

        $a->refresh();

        $this->assertSame('Sebelum ekspor', $a->judul);
        $this->assertSame('PT Uji', $a->profil['organisasi'] ?? null);
        $this->assertContains('Program penanda', array_column($a->programs, 'opsi'));
    }

    public function test_impor_mengabaikan_kunci_yang_tidak_dikenali(): void
    {
        $this->admin();

        $this->post('/tpkkp/data/impor', ['json' => json_encode([
            'judul'          => 'Judul baru',
            'kunci_karangan' => ['apa saja'],
        ])])->assertRedirect();

        $a = TpkkpAssessment::firstOrFail();

        $this->assertSame('Judul baru', $a->judul);
        $this->assertArrayNotHasKey('kunci_karangan', $a->getAttributes());
    }

    public function test_impor_menolak_json_yang_tidak_terbaca(): void
    {
        $this->admin();

        $this->post('/tpkkp/data/impor', ['json' => '{bukan json'])
            ->assertSessionHasErrors('json');
    }

    /* ══════════════ kosongkan ══════════════ */

    public function test_kosongkan_menghapus_nilai_tapi_menyisakan_yang_lain(): void
    {
        // Roster, profil, program, dan jadwal sengaja tidak ikut terhapus;
        // yang dikosongkan hanya nilai penilaiannya.
        $this->admin();

        $this->post('/tpkkp/profil', ['organisasi' => 'PT Tetap Ada']);

        $a = TpkkpAssessment::firstOrFail();
        $a->scores = ['TD' => ['1.1.1' => ['PT CAM' => 4]]];
        $a->save();

        $this->post('/tpkkp/data/reset')->assertRedirect();

        $a->refresh();

        $this->assertSame([], $a->scores);
        $this->assertSame('PT Tetap Ada', $a->profil['organisasi'] ?? null);
        $this->assertNotEmpty($a->roster);
        $this->assertNotEmpty($a->programs);
    }

    public function test_kosongkan_menyimpan_salinan_lebih_dulu(): void
    {
        // Tindakannya tidak bisa dibatalkan dari layar, jadi salinannya
        // adalah satu-satunya jalan pulang.
        $this->admin();
        $this->get('/tpkkp/data')->assertOk();

        $a = TpkkpAssessment::firstOrFail();
        $a->scores = ['TD' => ['1.1.1' => ['PT CAM' => 4]]];
        $a->save();

        $sebelum = glob(storage_path('app/tpkkp-*-sebelum-reset-*.json')) ?: [];

        $this->post('/tpkkp/data/reset')->assertRedirect();

        $sesudah = glob(storage_path('app/tpkkp-*-sebelum-reset-*.json')) ?: [];
        $baru    = array_values(array_diff($sesudah, $sebelum));

        // Tepat satu, bukan "paling sedikit satu": nama berkasnya harus
        // unik, dan dua pengosongan berurutan tidak boleh saling menimpa.
        $this->assertCount(1, $baru, 'Tidak ada salinan yang tersimpan sebelum pengosongan.');

        $isi = json_decode(file_get_contents($baru[0]), true);
        $this->assertSame(4, $isi['scores']['TD']['1.1.1']['PT CAM'] ?? null);

        unlink($baru[0]);
    }

    /* ══════════════ hak akses ══════════════ */

    public function test_bukan_admin_tidak_dapat_mengimpor_atau_mengosongkan(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/tpkkp/data')->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->where('bisaSunting', false)
        );

        $this->post('/tpkkp/data/impor', ['json' => '{}'])->assertForbidden();
        $this->post('/tpkkp/data/reset')->assertForbidden();
    }

    public function test_bukan_admin_tetap_boleh_mengekspor(): void
    {
        // Ekspor hanya membaca; melarangnya akan menghalangi orang
        // mengambil salinan datanya sendiri tanpa alasan.
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/tpkkp/data/ekspor')->assertOk();
    }

    public function test_tamu_tidak_dapat_membuka(): void
    {
        $this->get('/tpkkp/data')->assertRedirect(route('login'));
    }
}
