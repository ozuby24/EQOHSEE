<?php

namespace Tests\Feature;

use App\Http\Controllers\TpkkpController;
use App\Models\{TpkkpAssessment, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * PTPKKP — Program Improvement (Inertia).
 *
 * Halaman pertama di PTPKKP yang punya tiga bentuk perubahan sekaligus:
 * tambah, perbarui status, hapus. Programnya disimpan sebagai larik JSON,
 * bukan tabel, jadi tidak ada kunci basis data yang menjaga bentuknya —
 * baris tanpa id akan lolos begitu saja dan baru ketahuan ketika tombol
 * hapusnya tidak mengenai apa pun.
 */
class TpkkpProgramTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $u = User::factory()->create(['is_admin' => true]);
        $this->actingAs($u);

        return $u;
    }

    private function tambah(array $ganti = []): void
    {
        $this->post('/tpkkp/program', $ganti + [
            'param' => '2.1',
            'opsi'  => 'Menyusun ulang jadwal inspeksi rutin.',
        ])->assertRedirect();
    }

    private function props(): array
    {
        return $this->get('/tpkkp/program')->assertOk()->viewData('page')['props'];
    }

    /**
     * Baris terakhir daftar.
     *
     * Tiap periode dibuat dengan program bawaan dari instrumen, jadi daftar
     * tidak pernah kosong. Menguji dengan indeks 0 akan mengenai baris
     * bawaan itu, bukan baris yang baru saja ditambah, dan ujinya lulus
     * tanpa pernah menyentuh penambahannya.
     */
    private function terakhir(): array
    {
        $r = $this->props()['program'];

        return $r[count($r) - 1];
    }

    /* ══════════════ prop halaman ══════════════ */

    public function test_halaman_dirender_inertia_bukan_blade(): void
    {
        $this->admin();

        $this->get('/tpkkp/program')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Tpkkp/Program')
            ->has('judul')->has('subjudul')->has('picker')->has('tahun')
            ->has('saran')->has('program')->has('statusPilihan')
            ->where('bisaSunting', true));
    }

    /**
     * Pilihan status berasal dari sumber yang sama dengan aturan validasi.
     *
     * Sempat ditulis dua kali. Daftar semacam itu diam saja ketika salah
     * satunya bertambah: pilihannya muncul di layar, dipilih orang, lalu
     * ditolak validasi tanpa alasan yang tampak.
     */
    public function test_pilihan_status_seluruhnya_diterima_validasi(): void
    {
        $this->admin();
        $this->tambah();

        $id = $this->terakhir()['id'];

        foreach ($this->props()['statusPilihan'] as $status) {
            $this->put("/tpkkp/program/{$id}/status", ['status' => $status, 'progress' => 10])
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(TpkkpController::STATUS_PROGRAM, $this->props()['statusPilihan']);
    }

    public function test_saran_terurut_dari_selisih_paling_buruk(): void
    {
        $this->admin();

        $gap = array_column($this->props()['saran'], 'gap');
        $urut = $gap;
        sort($urut);

        $this->assertSame($urut, $gap, 'Saran tidak terurut menaik menurut selisih.');
        $this->assertLessThanOrEqual(8, count($gap));
    }

    /* ══════════════ bentuk baris ══════════════ */

    public function test_setiap_baris_membawa_seluruh_kunci_dan_tanpa_null(): void
    {
        // Vue membaca r.durasi dan kawan-kawan langsung; kunci yang hilang
        // menjadi undefined dan tercetak apa adanya di layar.
        $this->admin();
        $this->tambah();

        $r = $this->terakhir();

        foreach (['param', 'opsi', 'durasi', 'sasaran', 'target', 'status'] as $k) {
            $this->assertArrayHasKey($k, $r);
            $this->assertIsString($r[$k], "Kunci {$k} bukan string.");
        }

        $this->assertIsInt($r['progress']);
        $this->assertNotNull($r['id']);
    }

    public function test_baris_lama_tanpa_id_tetap_ditampilkan(): void
    {
        // Data yang tersimpan sebelum id dipakai tidak boleh menghilang;
        // yang disembunyikan hanya tombol yang memang butuh id.
        $this->admin();
        $this->get('/tpkkp/program')->assertOk();

        $a = TpkkpAssessment::firstOrFail();
        $a->programs = [['param' => '1.1', 'opsi' => 'Program lawas tanpa id']];
        $a->save();

        $r = $this->props()['program'][0];

        $this->assertNull($r['id']);
        $this->assertSame('Program lawas tanpa id', $r['opsi']);
        $this->assertSame(TpkkpController::STATUS_PROGRAM[0], $r['status']);
        $this->assertSame(0, $r['progress']);
    }

    /* ══════════════ ubah ══════════════ */

    public function test_tambah_lalu_terbaca_di_prop(): void
    {
        $this->admin();

        $sebelum = count($this->props()['program']);

        $this->tambah(['durasi' => '3 bulan', 'sasaran' => 'Seluruh site', 'target' => '100%']);

        $this->assertCount($sebelum + 1, $this->props()['program']);

        $r = $this->terakhir();

        $this->assertSame('2.1', $r['param']);
        $this->assertSame('3 bulan', $r['durasi']);
    }

    public function test_perbarui_status_dan_progres(): void
    {
        $this->admin();
        $this->tambah();

        $id = $this->terakhir()['id'];

        $this->put("/tpkkp/program/{$id}/status", ['status' => 'Berjalan', 'progress' => 45])
            ->assertRedirect();

        $r = $this->terakhir();

        $this->assertSame('Berjalan', $r['status']);
        $this->assertSame(45, $r['progress']);
    }

    public function test_hapus_membuang_hanya_baris_yang_dituju(): void
    {
        $this->admin();

        $sebelum = count($this->props()['program']);
        $this->tambah(['param' => 'UJI-A']);
        $this->tambah(['param' => 'UJI-B']);

        $program = $this->props()['program'];
        $this->assertCount($sebelum + 2, $program);

        $buang = $program[$sebelum]['id'];
        $this->delete("/tpkkp/program/{$buang}")->assertRedirect();

        $sisa = $this->props()['program'];

        $this->assertCount($sebelum + 1, $sisa);
        $this->assertSame('UJI-B', $this->terakhir()['param']);
        $this->assertNotContains('UJI-A', array_column($sisa, 'param'));
    }

    public function test_status_di_luar_daftar_ditolak(): void
    {
        $this->admin();
        $this->tambah();

        $id = $this->terakhir()['id'];

        $this->put("/tpkkp/program/{$id}/status", ['status' => 'Entahlah'])
            ->assertSessionHasErrors('status');
    }

    /* ══════════════ hak akses ══════════════ */

    public function test_bukan_admin_tidak_dapat_mengubah(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]));

        $this->get('/tpkkp/program')->assertOk()->assertInertia(
            fn (AssertableInertia $p) => $p->where('bisaSunting', false)
        );

        $this->post('/tpkkp/program', ['param' => '1.1', 'opsi' => 'x'])->assertForbidden();
    }

    public function test_tamu_tidak_dapat_membuka(): void
    {
        $this->get('/tpkkp/program')->assertRedirect(route('login'));
    }

    /**
     * Dua program yang ditambahkan berturut-turut tidak boleh ber-id sama.
     *
     * Id-nya dulu 'p' . timestamp . rand(10, 99) — dua program dalam detik
     * yang sama bertabrakan satu kali dari sembilan puluh. Menambahkan dua
     * program berturut-turut adalah cara normal mengisi rencana perbaikan,
     * bukan keadaan langka.
     *
     * Akibatnya tidak berhenti pada id kembar: destroyProgram menyaring
     * dengan `!== $id`, jadi menghapus satu program menghapus keduanya.
     * Kehilangannya diam — tidak ada galat, hanya satu baris yang ikut
     * lenyap.
     *
     * Diuji dengan dua puluh baris, bukan dua. Pada peluang satu per
     * sembilan puluh, uji dua baris LULUS sembilan puluh sembilan kali
     * dari seratus — dan uji yang hampir selalu lulus atas cacat yang
     * nyata lebih buruk daripada tidak ada uji sama sekali.
     */
    public function test_id_program_tidak_pernah_kembar(): void
    {
        $this->admin();

        for ($i = 0; $i < 20; $i++) {
            $this->tambah(['param' => 'U-' . $i]);
        }

        $id = array_column($this->props()['program'], 'id');

        $this->assertSame(count($id), count(array_unique($id)),
            'Ada program ber-id kembar; menghapus salah satunya akan membuang keduanya.');
    }

    /** Dan menghapus satu memang hanya membuang satu. */
    public function test_hapus_hanya_membuang_satu_walau_ditambah_beruntun(): void
    {
        $this->admin();

        $sebelum = count($this->props()['program']);
        for ($i = 0; $i < 20; $i++) {
            $this->tambah(['param' => 'U-' . $i]);
        }

        $program = $this->props()['program'];
        $this->delete('/tpkkp/program/' . $program[$sebelum]['id'])->assertRedirect();

        $this->assertCount($sebelum + 19, $this->props()['program'],
            'Lebih dari satu program terbuang oleh satu penghapusan.');
    }
}
