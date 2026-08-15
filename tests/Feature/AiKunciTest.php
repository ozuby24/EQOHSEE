<?php

namespace Tests\Feature;

use App\Models\{ActivityLog, User};
use App\Support\{Ai, AiPenyedia};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Http};
use Tests\TestCase;

/**
 * Kunci API milik pemasangnya sendiri.
 *
 * Sebuah kunci API adalah alat bayar: yang memegangnya dapat
 * membelanjakan tagihan orang lain sampai batas kuotanya. Karena itu
 * yang diuji di sini bukan "apakah asistennya menjawab" melainkan hal
 * yang jauh lebih sunyi bila salah:
 *
 * 1. Kuncinya tidak pernah tersimpan sebagai teks biasa. Satu cadangan
 *    basis data yang tersalin sudah cukup untuk membocorkannya.
 * 2. Kuncinya tidak pernah dikirim balik ke peramban. Halaman
 *    pengaturan yang memulangkan kunci untuk "kemudahan menyunting"
 *    membocorkannya ke setiap ekstensi peramban dan setiap tangkapan
 *    layar.
 * 3. Kuncinya tidak pernah masuk log aktivitas.
 *
 * Ketiganya tidak menimbulkan galat apa pun bila dilanggar.
 */
class AiKunciTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** Kunci uji yang sengaja mudah dicari di dalam teks apa pun. */
    private const KUNCI = 'sk-ant-kunci-uji-RAHASIA-jangan-bocor-9876';

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    /* ---------- penyimpanan ---------- */

    public function test_kunci_tidak_tersimpan_sebagai_teks_biasa(): void
    {
        Ai::simpanKunci(AiPenyedia::ANTHROPIC, self::KUNCI);

        $mentah = DB::table('app_settings')->where('key', 'ai_kunci_anthropic')->value('value');

        $this->assertNotNull($mentah);
        $this->assertStringNotContainsString('RAHASIA', $mentah,
            'Kunci tersimpan apa adanya di basis data.');
        $this->assertStringNotContainsString(self::KUNCI, $mentah);

        // Tetap dapat dibaca kembali oleh aplikasinya sendiri.
        $this->assertSame(self::KUNCI, Ai::kunci(AiPenyedia::ANTHROPIC));
    }

    /**
     * Seluruh isi tabel pengaturan disapu, bukan hanya barisnya sendiri.
     * Kunci yang tanpa sengaja ikut tersalin ke baris lain — nama model,
     * catatan — sama bocornya.
     */
    public function test_kunci_tidak_muncul_di_baris_pengaturan_mana_pun(): void
    {
        Ai::simpanKunci(AiPenyedia::OPENAI, self::KUNCI);
        Ai::simpanPengaturan(AiPenyedia::OPENAI, 'gpt-4.1', 1200);

        $semua = DB::table('app_settings')->get()->map(fn ($r) => $r->key.'='.$r->value)->implode("\n");

        $this->assertStringNotContainsString('RAHASIA', $semua);
    }

    public function test_kunci_tiap_penyedia_disimpan_terpisah(): void
    {
        Ai::simpanKunci(AiPenyedia::ANTHROPIC, 'kunci-anthropic-aaaa');
        Ai::simpanKunci(AiPenyedia::GEMINI, 'kunci-gemini-bbbb');

        $this->assertSame('kunci-anthropic-aaaa', Ai::kunci(AiPenyedia::ANTHROPIC));
        $this->assertSame('kunci-gemini-bbbb', Ai::kunci(AiPenyedia::GEMINI));
    }

    /** Berpindah penyedia tidak boleh membuang kunci yang sudah ada. */
    public function test_berpindah_penyedia_tidak_membuang_kunci_lama(): void
    {
        Ai::simpanKunci(AiPenyedia::ANTHROPIC, 'kunci-anthropic-aaaa');
        Ai::simpanPengaturan(AiPenyedia::ANTHROPIC, null, null);

        Ai::simpanPengaturan(AiPenyedia::OPENAI, null, null);

        $this->assertSame('kunci-anthropic-aaaa', Ai::kunci(AiPenyedia::ANTHROPIC));
    }

    public function test_hapus_kunci_hanya_membuang_penyedia_yang_dituju(): void
    {
        Ai::simpanKunci(AiPenyedia::ANTHROPIC, 'kunci-anthropic-aaaa');
        Ai::simpanKunci(AiPenyedia::GEMINI, 'kunci-gemini-bbbb');

        Ai::hapusKunci(AiPenyedia::ANTHROPIC);

        $this->assertNull(Ai::kunci(AiPenyedia::ANTHROPIC));
        $this->assertSame('kunci-gemini-bbbb', Ai::kunci(AiPenyedia::GEMINI));
    }

    public function test_ekor_kunci_hanya_memperlihatkan_empat_huruf_terakhir(): void
    {
        Ai::simpanKunci(AiPenyedia::ANTHROPIC, self::KUNCI);

        $ekor = Ai::ekorKunci(AiPenyedia::ANTHROPIC);

        $this->assertStringEndsWith('9876', $ekor);
        $this->assertStringNotContainsString('RAHASIA', $ekor);
    }

    /* ---------- tidak bocor ke peramban ---------- */

    public function test_halaman_pengaturan_tidak_memulangkan_kunci(): void
    {
        Ai::simpanKunci(AiPenyedia::ANTHROPIC, self::KUNCI);
        Ai::simpanPengaturan(AiPenyedia::ANTHROPIC, null, null);

        $r = $this->actingAs($this->admin)->get(route('admin.ai'))->assertOk();

        // Seluruh badan tanggapan, bukan hanya prop yang diperiksa satu-satu.
        $this->assertStringNotContainsString('RAHASIA', $r->getContent());
        $this->assertStringNotContainsString(self::KUNCI, $r->getContent());

        $r->assertInertia(function ($p) {
            $props = $p->toArray()['props'];

            $this->assertStringNotContainsString('RAHASIA', json_encode($props));

            $anthropic = collect($props['penyedia'])->firstWhere('kode', 'anthropic');
            $this->assertTrue($anthropic['terpasang']);
            $this->assertStringEndsWith('9876', $anthropic['ekor']);
        });
    }

    public function test_kunci_tidak_masuk_log_aktivitas(): void
    {
        $this->actingAs($this->admin)->post(route('admin.ai.simpan'), [
            'penyedia' => AiPenyedia::ANTHROPIC,
            'kunci'    => self::KUNCI,
        ])->assertSessionHasNoErrors();

        $log = ActivityLog::all()->map(fn ($l) => $l->action.' '.$l->detail)->implode("\n");

        $this->assertNotEmpty($log);
        $this->assertStringNotContainsString('RAHASIA', $log);
    }

    /* ---------- hak akses ---------- */

    public function test_bukan_admin_tidak_dapat_membuka_pengaturan(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.ai'))->assertForbidden();
    }

    public function test_bukan_admin_tidak_dapat_menyimpan_kunci(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('admin.ai.simpan'), [
                'penyedia' => AiPenyedia::ANTHROPIC, 'kunci' => self::KUNCI,
            ])->assertForbidden();

        $this->assertNull(Ai::kunci(AiPenyedia::ANTHROPIC));
    }

    public function test_penyedia_yang_tidak_dikenal_ditolak(): void
    {
        $this->actingAs($this->admin)->post(route('admin.ai.simpan'), [
            'penyedia' => 'penyedia-karangan', 'kunci' => self::KUNCI,
        ])->assertSessionHasErrors('penyedia');
    }

    /* ---------- menyimpan tanpa membuang ---------- */

    /**
     * Kolom kunci yang dikosongkan berarti "biarkan yang sudah ada".
     * Menyamakannya dengan "hapus" membuat setiap penyuntingan model
     * tanpa sengaja mematikan asistennya.
     */
    public function test_menyimpan_tanpa_mengisi_kunci_tidak_menghapusnya(): void
    {
        Ai::simpanKunci(AiPenyedia::ANTHROPIC, self::KUNCI);

        $this->actingAs($this->admin)->post(route('admin.ai.simpan'), [
            'penyedia' => AiPenyedia::ANTHROPIC,
            'model'    => 'claude-opus-5',
        ])->assertSessionHasNoErrors();

        $this->assertSame(self::KUNCI, Ai::kunci(AiPenyedia::ANTHROPIC));
        $this->assertSame('claude-opus-5', Ai::model());
    }

    /* ---------- pemanggilan penyedia ---------- */

    public function test_kunci_dititipkan_sesuai_cara_tiap_penyedia(): void
    {
        Http::fake(['*' => Http::response(['content' => [['type' => 'text', 'text' => 'halo']]])]);

        Ai::simpanKunci(AiPenyedia::ANTHROPIC, self::KUNCI);
        Ai::simpanPengaturan(AiPenyedia::ANTHROPIC, 'claude-sonnet-5', null);

        $hasil = Ai::jawab([['peran' => 'pengguna', 'isi' => 'halo']], 'Peran uji');

        $this->assertTrue($hasil['ok']);
        $this->assertSame('halo', $hasil['isi']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.anthropic.com/v1/messages')
            && $r->hasHeader('x-api-key', self::KUNCI)
            && $r->hasHeader('anthropic-version')
            && $r['model'] === 'claude-sonnet-5');
    }

    public function test_openai_memakai_tajuk_bearer(): void
    {
        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => 'ok']]]])]);

        Ai::simpanKunci(AiPenyedia::OPENAI, self::KUNCI);
        Ai::simpanPengaturan(AiPenyedia::OPENAI, 'gpt-4.1', null);

        $this->assertSame('ok', Ai::jawab([['peran' => 'pengguna', 'isi' => 'p']], 'Peran')['isi']);

        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer '.self::KUNCI));
    }

    public function test_gemini_tetap_bekerja_seperti_sebelumnya(): void
    {
        Http::fake(['*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'jawab gemini']]]]],
        ])]);

        Ai::simpanKunci(AiPenyedia::GEMINI, self::KUNCI);
        Ai::simpanPengaturan(AiPenyedia::GEMINI, 'gemini-flash-latest', null);

        $this->assertSame('jawab gemini', Ai::jawab([['peran' => 'pengguna', 'isi' => 'p']], 'Peran')['isi']);

        Http::assertSent(fn ($r) => $r->hasHeader('x-goog-api-key', self::KUNCI));
    }

    public function test_tanpa_kunci_asisten_menolak_menjawab(): void
    {
        Http::fake();

        $hasil = Ai::jawab([['peran' => 'pengguna', 'isi' => 'p']], 'Peran');

        $this->assertFalse($hasil['ok']);
        Http::assertNothingSent();
    }

    /**
     * Pesan galat penyedia kadang mengutip kembali kunci yang dikirim.
     * Yang ditampilkan kepada administrator harus sudah disamarkan.
     */
    public function test_kunci_disamarkan_di_pesan_galat(): void
    {
        Http::fake(['*' => Http::response(
            ['error' => ['message' => 'Invalid key: '.self::KUNCI]], 401,
        )]);

        $hasil = Ai::uji(AiPenyedia::ANTHROPIC, self::KUNCI);

        $this->assertFalse($hasil['ok']);
        $this->assertStringNotContainsString('RAHASIA', $hasil['pesan']);
        $this->assertStringContainsString('disamarkan', $hasil['pesan']);
    }

    /**
     * Uji memakai kunci yang baru diketik, bukan yang tersimpan —
     * kunci salah ketik yang langsung tersimpan mematikan asisten
     * sampai ada yang menyadarinya.
     */
    public function test_uji_tidak_menyimpan_kunci_yang_diujinya(): void
    {
        Http::fake(['*' => Http::response(['content' => [['type' => 'text', 'text' => 'OK']]])]);

        $this->actingAs($this->admin)->post(route('admin.ai.uji'), [
            'penyedia' => AiPenyedia::ANTHROPIC,
            'kunci'    => self::KUNCI,
        ])->assertSessionHasNoErrors();

        $this->assertNull(Ai::kunci(AiPenyedia::ANTHROPIC),
            'Kunci ikut tersimpan padahal baru diuji.');
    }
}
