<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\{Ai, AiPenyedia};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pendamping AI untuk halaman diagnosa.
 *
 * Yang dijaga di sini bukan mutu jawabannya — itu urusan modelnya —
 * melainkan APA YANG DIKIRIM keluar. Setiap pertanyaan meninggalkan
 * server ini menuju penyedia pihak ketiga, dan yang ikut terbawa tidak
 * dapat ditarik kembali.
 *
 * Batasnya: hanya daftar temuan diagnosa. Bukan isi basis data, bukan
 * `.env`, bukan potongan kode, bukan nama pengguna. Temuan itu sendiri
 * memang menyebut keadaan sistem — jumlah baris tanpa pemilik, nama
 * tabel — dan itulah memang yang perlu ditafsirkan; yang tidak boleh
 * ikut adalah isinya.
 */
class AiDiagnosaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
    }

    private function nyalakan(): void
    {
        Ai::simpanKunci(AiPenyedia::ANTHROPIC, 'kunci-uji-anthropic-1234');
        Ai::simpanPengaturan(AiPenyedia::ANTHROPIC, 'claude-sonnet-5', null);
    }

    /* ---------- hak akses ---------- */

    public function test_bukan_admin_tidak_dapat_bertanya(): void
    {
        Http::fake();
        $this->nyalakan();

        $this->actingAs(User::factory()->create())
            ->post(route('admin.ai.diagnosa'), ['pertanyaan' => 'apa?'])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    /* ---------- tanpa kunci ---------- */

    public function test_tanpa_kunci_tidak_ada_permintaan_keluar(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->post(route('admin.ai.diagnosa'))
            ->assertSessionHasErrors('ai');

        Http::assertNothingSent();
    }

    public function test_halaman_diagnosa_menawarkan_pengaturan_bila_belum_aktif(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.system.diagnosa'))
            ->assertInertia(function ($p) {
                $ai = $p->toArray()['props']['ai'];

                $this->assertFalse($ai['aktif']);
                $this->assertSame(route('admin.ai'), $ai['atur']);
            });
    }

    /* ---------- apa yang dikirim keluar ---------- */

    public function test_yang_dikirim_hanya_temuan_diagnosa(): void
    {
        Http::fake(['*' => Http::response(['content' => [['type' => 'text', 'text' => 'saran']]])]);
        $this->nyalakan();

        $this->actingAs($this->admin)
            ->post(route('admin.ai.diagnosa'), ['pertanyaan' => 'Mana dulu?'])
            ->assertSessionHasNoErrors();

        Http::assertSent(function ($r) {
            $badan = json_encode($r->data());

            // Temuan memang ikut — itu memang yang ditafsirkan.
            $this->assertStringContainsString('Temuan diagnosa sistem', $badan);
            $this->assertStringContainsString('Mana dulu?', $badan);

            /* Yang tidak boleh ikut: rahasia apa pun. Kunci AI sendiri
               paling mudah tanpa sengaja terbawa, sebab ia berada di
               tabel pengaturan yang sama dengan yang dibaca diagnosa. */
            $this->assertStringNotContainsString('kunci-uji-anthropic-1234', $badan);
            $this->assertStringNotContainsString('APP_KEY', $badan);
            $this->assertStringNotContainsString(config('app.key'), $badan);

            return true;
        });
    }

    public function test_jawaban_dibawa_ke_halaman_lewat_pesan_kilat(): void
    {
        Http::fake(['*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Tangani izin .env lebih dulu.']],
        ])]);
        $this->nyalakan();

        $this->actingAs($this->admin)
            ->post(route('admin.ai.diagnosa'))
            ->assertSessionHas('aiJawaban', 'Tangani izin .env lebih dulu.');
    }

    public function test_galat_penyedia_dilaporkan_bukan_ditelan(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'kuota habis']], 429)]);
        $this->nyalakan();

        $this->actingAs($this->admin)
            ->post(route('admin.ai.diagnosa'))
            ->assertSessionHasErrors('ai');
    }

    /**
     * Pertanyaan yang terlalu panjang ditolak sebelum dikirim. Batasnya
     * bukan soal kerapian: tiap huruf yang lewat dibayar pemasangnya.
     */
    public function test_pertanyaan_terlalu_panjang_ditolak_sebelum_dikirim(): void
    {
        Http::fake();
        $this->nyalakan();

        $this->actingAs($this->admin)
            ->post(route('admin.ai.diagnosa'), ['pertanyaan' => str_repeat('a', 501)])
            ->assertSessionHasErrors('pertanyaan');

        Http::assertNothingSent();
    }

    /**
     * Kegagalan AI harus SAMPAI ke halamannya, bukan berhenti di sesi.
     *
     * Pesannya sudah lama dibuat dengan benar di server, lalu tidak
     * pernah digambar: halaman diagnosa hanya punya tempat untuk
     * `galat.perbaikan`. Kunci yang salah, kuota yang habis, dan nama
     * model yang tidak dikenal karena itu terlihat persis sama —
     * tombolnya kembali normal dan tidak ada apa pun yang muncul.
     *
     * Dijaga dari dua sisi: pesannya ada di sesi, DAN halamannya
     * memuat tempat untuk menggambarnya. Yang pertama saja pernah
     * benar selama berbulan-bulan tanpa yang kedua.
     */
    public function test_kegagalan_ai_sampai_ke_halaman(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'kuota habis']], 429)]);
        $this->nyalakan();

        $this->actingAs($this->admin)
            ->post(route('admin.ai.diagnosa'))
            ->assertSessionHasErrors('ai');

        $halaman = (string) file_get_contents(resource_path('js/Pages/Admin/Diagnosa.vue'));

        $this->assertStringContainsString('galat.ai', $halaman,
            'Halaman diagnosa tidak punya tempat untuk menggambar kegagalan AI, '
            .'sehingga pesannya dibuat lalu dibuang.');
    }

    /**
     * Satu pertanyaan berarti SATU permintaan, berapa pun temuannya.
     *
     * Mengirim satu permintaan per temuan terasa lebih rapi dan
     * membuat tagihan naik berlipat tanpa ada yang memutuskannya —
     * pada dua puluh tiga pemeriksaan, itu dua puluh tiga kali lipat.
     */
    public function test_satu_pertanyaan_satu_permintaan(): void
    {
        Http::fake(['*' => Http::response(['content' => [['type' => 'text', 'text' => 'saran']]])]);
        $this->nyalakan();

        $this->actingAs($this->admin)->post(route('admin.ai.diagnosa'));

        $jumlah = 0;
        Http::assertSent(function () use (&$jumlah) { $jumlah++; return true; });

        $this->assertSame(1, $jumlah, "Terkirim {$jumlah} permintaan untuk satu pertanyaan.");
    }
}
