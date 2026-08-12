<?php

namespace Tests\Feature;

use App\Models\{Company, Percakapan, User};
use App\Support\AsistenAI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Bantuan — asisten AI dan kotak masuk admin.
 *
 * Panggilan ke Gemini dipalsukan di seluruh berkas ini: uji yang benar-benar
 * menembak penyedia luar akan gagal ketika jaringan mati, kuota habis, atau
 * modelnya berganti nama — tiga hal yang tidak ada hubungannya dengan kode
 * yang sedang diuji.
 */
class BantuanTest extends TestCase
{
    use RefreshDatabase;

    private function aiHidup(string $jawab = 'Silakan buka menu PTPKKP → Formulir Nilai.'): void
    {
        config(['bantuan.ai.kunci' => 'kunci-uji']);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => $jawab]]]]],
        ])]);
    }

    private function aiMati(): void
    {
        config(['bantuan.ai.kunci' => null]);
        Http::preventStrayRequests();
    }

    private function masuk(bool $admin = false): User
    {
        $u = User::factory()->create(['is_admin' => $admin]);
        $this->actingAs($u);

        return $u;
    }

    /* ══════════════ halaman ══════════════ */

    public function test_halaman_bantuan_dirender_inertia(): void
    {
        $this->aiMati();
        $this->masuk();

        $this->get('/bantuan')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Bantuan/Kotak')
            ->has('judul')->has('subjudul')->has('pesan')
            ->where('aiAktif', false)
            ->where('admin', false));
    }

    public function test_utas_dibuat_sekali_lalu_dipakai_ulang(): void
    {
        // Satu utas per pengguna: membuat utas baru tiap kali halaman dibuka
        // akan memecah riwayat, dan admin membaca potongan tanpa konteks.
        $this->aiMati();
        $this->masuk();

        $this->get('/bantuan')->assertOk();
        $this->get('/bantuan')->assertOk();

        $this->assertSame(1, Percakapan::count());
    }

    /* ══════════════ asisten AI ══════════════ */

    public function test_asisten_menjawab_dan_jawabannya_tersimpan_di_utas(): void
    {
        $this->aiHidup('Buka PTPKKP → Formulir Nilai.');
        $this->masuk();

        $this->post('/bantuan', ['isi' => 'Di mana mengisi nilai PTPKKP?', 'saluran' => 'ai'])
            ->assertRedirect();

        $pesan = $this->get('/bantuan')->assertOk()->viewData('page')['props']['pesan'];

        $this->assertSame(['pengguna', 'asisten'], array_column($pesan, 'peran'));
        $this->assertSame('Buka PTPKKP → Formulir Nilai.', $pesan[1]['isi']);
    }

    public function test_riwayat_yang_dikirim_ke_penyedia_memakai_peran_yang_dikenalinya(): void
    {
        // Gemini hanya mengenal 'user' dan 'model'. Mengirim 'asisten' apa
        // adanya membuat permintaannya ditolak — dan penolakannya baru terlihat
        // sebagai jawaban gagal, bukan sebagai galat yang jelas.
        $this->aiHidup();
        $this->masuk();

        $this->post('/bantuan', ['isi' => 'Pertanyaan pertama', 'saluran' => 'ai']);
        $this->post('/bantuan', ['isi' => 'Pertanyaan kedua', 'saluran' => 'ai']);

        Http::assertSent(function ($r) {
            $peran = array_column($r['contents'] ?? [], 'role');

            foreach ($peran as $p) {
                $this->assertContains($p, ['user', 'model'], "Peran '{$p}' tidak dikenali penyedia.");
            }

            // Giliran terakhir harus giliran pengguna, kalau tidak tidak ada
            // yang perlu dijawab.
            return $peran === [] || end($peran) === 'user';
        });
    }

    public function test_kegagalan_asisten_tersimpan_sebagai_pesan_sistem(): void
    {
        // Kegagalan yang hanya lewat sebagai notifikasi membuat orang mengira
        // pertanyaannya tidak terkirim, lalu mengetiknya lagi.
        config(['bantuan.ai.kunci' => 'kunci-uji']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(
            ['error' => ['message' => 'model not found']], 404
        )]);

        $this->masuk();

        $this->post('/bantuan', ['isi' => 'Halo', 'saluran' => 'ai'])->assertRedirect();

        $pesan = $this->get('/bantuan')->assertOk()->viewData('page')['props']['pesan'];

        $this->assertSame(['pengguna', 'sistem'], array_column($pesan, 'peran'));
        $this->assertStringNotContainsString('model not found', $pesan[1]['isi'],
            'Isi galat penyedia tidak boleh sampai ke layar pemakai.');
    }

    public function test_jawaban_kosong_tidak_menjadi_gelembung_kosong(): void
    {
        // Keluaran yang terpotong batas token memulangkan bagian kosong.
        config(['bantuan.ai.kunci' => 'kunci-uji']);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => '   ']]], 'finishReason' => 'MAX_TOKENS']],
        ])]);

        $this->masuk();
        $this->post('/bantuan', ['isi' => 'Halo', 'saluran' => 'ai'])->assertRedirect();

        $pesan = $this->get('/bantuan')->assertOk()->viewData('page')['props']['pesan'];

        $this->assertSame('sistem', $pesan[1]['peran']);
        $this->assertNotSame('', trim($pesan[1]['isi']));
    }

    public function test_tanpa_kunci_asisten_tidak_menghubungi_siapa_pun(): void
    {
        // Yang dijaga bukan pesannya melainkan bahwa tidak ada permintaan
        // keluar sama sekali — preventStrayRequests menggagalkan uji ini bila
        // ada satu pun panggilan yang lolos.
        $this->aiMati();
        $this->masuk();

        $this->post('/bantuan', ['isi' => 'Halo', 'saluran' => 'ai'])->assertRedirect();

        $pesan = $this->get('/bantuan')->assertOk()->viewData('page')['props']['pesan'];

        $this->assertSame('sistem', $pesan[1]['peran']);
        $this->assertFalse(AsistenAI::aktif());
    }

    /* ══════════════ saluran admin ══════════════ */

    public function test_kirim_ke_admin_tidak_memanggil_asisten(): void
    {
        // Asistennya hidup, tapi salurannya admin — tidak boleh ada panggilan.
        $this->aiHidup();
        $this->masuk();

        $this->post('/bantuan', ['isi' => 'Tolong reset kata sandi saya', 'saluran' => 'admin'])
            ->assertRedirect();

        Http::assertNothingSent();

        $pesan = $this->get('/bantuan')->assertOk()->viewData('page')['props']['pesan'];

        $this->assertSame(['pengguna', 'sistem'], array_column($pesan, 'peran'));
    }

    public function test_saluran_di_luar_daftar_ditolak(): void
    {
        $this->aiMati();
        $this->masuk();

        $this->post('/bantuan', ['isi' => 'Halo', 'saluran' => 'entah'])
            ->assertSessionHasErrors('saluran');
    }

    /* ══════════════ kotak masuk admin ══════════════ */

    public function test_bukan_admin_tidak_dapat_membuka_kotak_masuk(): void
    {
        $this->aiMati();
        $this->masuk();

        $this->get('/bantuan/masuk')->assertForbidden();
    }

    public function test_admin_melihat_utas_dan_dapat_membalas(): void
    {
        $this->aiMati();

        $orang = User::factory()->create(['is_admin' => false, 'name' => 'Budi']);
        $this->actingAs($orang);
        $this->post('/bantuan', ['isi' => 'Kenapa sertifikat saya belum terbit?', 'saluran' => 'admin']);

        $this->masuk(admin: true);

        $props = $this->get('/bantuan/masuk')->assertOk()->viewData('page')['props'];

        $this->assertCount(1, $props['utas']);
        $this->assertSame('Budi', $props['utas'][0]['nama']);

        $this->post("/bantuan/{$props['terpilih']}/balas", ['isi' => 'Sertifikat terbit setelah kuis lulus.'])
            ->assertRedirect();

        // Pemakai membaca balasannya di utasnya sendiri.
        $this->actingAs($orang);
        $pesan = $this->get('/bantuan')->assertOk()->viewData('page')['props']['pesan'];

        $this->assertSame('admin', $pesan[count($pesan) - 1]['peran']);
    }

    public function test_utas_terbuka_selalu_di_atas_yang_selesai(): void
    {
        // Kotak masuk yang diurut waktu saja mengubur pertanyaan yang belum
        // dijawab di bawah percakapan lama yang sudah selesai.
        $this->aiMati();

        $lama = User::factory()->create(['name' => 'Lama']);
        $this->actingAs($lama);
        $this->post('/bantuan', ['isi' => 'Pertanyaan lama', 'saluran' => 'admin']);

        $baru = User::factory()->create(['name' => 'Baru']);
        $this->actingAs($baru);
        $this->post('/bantuan', ['isi' => 'Pertanyaan baru', 'saluran' => 'admin']);

        $admin = $this->masuk(admin: true);

        // Tandai utas terbaru selesai; yang lama harus naik ke atas.
        $idBaru = Percakapan::whereHas('peserta', fn ($q) => $q->where('users.id', $baru->id))->value('id');
        $this->post("/bantuan/{$idBaru}/selesai")->assertRedirect();

        $urut = array_column($this->get('/bantuan/masuk')->assertOk()->viewData('page')['props']['utas'], 'nama');

        $this->assertSame('Lama', $urut[0], 'Utas yang masih terbuka harus berada di atas.');
    }

    public function test_selesai_meninggalkan_jejak_di_utas_pemakai(): void
    {
        // Statusnya hanya terlihat admin; pemakai perlu tahu percakapannya
        // ditutup agar tidak menunggu balasan yang tidak akan datang.
        $this->aiMati();

        $orang = User::factory()->create();
        $this->actingAs($orang);
        $this->post('/bantuan', ['isi' => 'Halo', 'saluran' => 'admin']);

        $this->masuk(admin: true);
        $id = Percakapan::first()->id;
        $this->post("/bantuan/{$id}/selesai")->assertRedirect();

        $this->actingAs($orang);
        $props = $this->get('/bantuan')->assertOk()->viewData('page')['props'];

        $this->assertSame('selesai', $props['status']);
        $this->assertStringContainsString('selesai', end($props['pesan'])['isi']);
    }

    public function test_bukan_admin_tidak_dapat_membalas_atau_menutup(): void
    {
        $this->aiMati();

        $orang = User::factory()->create();
        $this->actingAs($orang);
        $this->post('/bantuan', ['isi' => 'Halo', 'saluran' => 'admin']);

        $id = Percakapan::first()->id;

        $this->post("/bantuan/{$id}/balas", ['isi' => 'balasan palsu'])->assertForbidden();
        $this->post("/bantuan/{$id}/selesai")->assertForbidden();
    }

    public function test_tamu_tidak_dapat_membuka_bantuan(): void
    {
        $this->get('/bantuan')->assertRedirect(route('login'));
    }
}
