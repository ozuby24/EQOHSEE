<?php

namespace Tests\Feature;

use App\Models\{Company, Percakapan, User};
use App\Support\Lencana;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Pesan — chat langsung antar pengguna dan grup perusahaan.
 *
 * Tidak ada penyedia luar yang dipanggil di sini seperti pada Bantuan, jadi
 * yang diuji adalah batas otorisasi, keanggotaan grup yang menyesuaikan
 * sendiri, dan pembuatan percakapan langsung yang idempoten.
 */
class ChatTest extends TestCase
{
    use RefreshDatabase;

    private static int $n = 0;

    private function perusahaan(array $x = []): Company
    {
        $i = ++self::$n;

        return Company::create(array_merge(['name' => "PT Tambang Uji {$i}", 'code' => "PTU{$i}"], $x));
    }

    private function masuk(bool $admin = false, ?Company $perusahaan = null): User
    {
        $u = User::factory()->create(['is_admin' => $admin, 'company_id' => $perusahaan?->id]);
        $this->actingAs($u);

        return $u;
    }

    /* ══════════════ halaman ══════════════ */

    public function test_halaman_pesan_dirender_inertia(): void
    {
        $this->masuk();

        $this->get('/pesan')->assertOk()->assertInertia(fn (AssertableInertia $p) => $p
            ->component('Pesan/Kotak')
            ->has('judul')->has('subjudul')->has('percakapan')->has('pesan')->has('penggunaId'));
    }

    public function test_tamu_tidak_dapat_membuka_pesan(): void
    {
        $this->get('/pesan')->assertRedirect(route('login'));
    }

    /* ══════════════ grup perusahaan — keanggotaan menyesuaikan sendiri ══════════════ */

    public function test_membuka_pesan_membuat_grup_perusahaan_dan_mendaftarkan_diri(): void
    {
        $perusahaan = $this->perusahaan(['name' => 'Tambang Jaya']);
        $this->masuk(perusahaan: $perusahaan);

        $props = $this->get('/pesan')->assertOk()->viewData('page')['props'];

        $this->assertCount(1, $props['percakapan']);
        $this->assertSame('grup', $props['percakapan'][0]['jenis']);
        $this->assertSame('Tambang Jaya', $props['percakapan'][0]['nama']);
    }

    public function test_keanggotaan_grup_menyesuaikan_ketika_perusahaan_berganti_nama(): void
    {
        $perusahaan = $this->perusahaan(['name' => 'Nama Lama']);
        $u = $this->masuk(perusahaan: $perusahaan);

        $this->get('/pesan');

        $perusahaan->update(['name' => 'Nama Baru']);

        // Guard sesi menyimpan pengguna yang sudah dimuat sepanjang metode uji
        // ini, termasuk relasi company-nya — masuk ulang dengan salinan segar
        // supaya permintaan berikut membaca nama yang baru, bukan cache PHP
        // yang tidak ada pada permintaan sungguhan yang selalu proses baru.
        $this->actingAs($u->fresh());

        $props = $this->get('/pesan')->assertOk()->viewData('page')['props'];

        $this->assertSame('Nama Baru', $props['percakapan'][0]['nama']);
    }

    public function test_pengguna_yang_pindah_perusahaan_ikut_pindah_grup(): void
    {
        $lama = $this->perusahaan(['name' => 'Perusahaan Lama']);
        $baru = $this->perusahaan(['name' => 'Perusahaan Baru']);

        $u = $this->masuk(perusahaan: $lama);
        $this->get('/pesan');

        $grupLama = Percakapan::where('company_id', $lama->id)->first();
        $this->assertTrue($grupLama->peserta()->where('users.id', $u->id)->exists());

        $u->update(['company_id' => $baru->id]);
        $this->actingAs($u->fresh());

        $this->get('/pesan');

        $grupLama->refresh();
        $this->assertFalse($grupLama->peserta()->where('users.id', $u->id)->exists(),
            'Pengguna yang sudah pindah tidak boleh tetap terdaftar di grup lama.');

        $grupBaru = Percakapan::where('company_id', $baru->id)->first();
        $this->assertTrue($grupBaru->peserta()->where('users.id', $u->id)->exists());
    }

    public function test_pesan_grup_terlihat_oleh_sesama_anggota_perusahaan(): void
    {
        $perusahaan = $this->perusahaan();

        $a = $this->masuk(perusahaan: $perusahaan);
        $this->get('/pesan');
        $grup = Percakapan::where('company_id', $perusahaan->id)->first();

        $this->post("/pesan/{$grup->id}", ['isi' => 'Halo semua'])->assertRedirect();

        $b = $this->masuk(perusahaan: $perusahaan);
        $props = $this->get('/pesan', ['percakapan' => $grup->id])
            ->assertOk()->viewData('page')['props'];

        $isi = array_column($props['pesan'], 'isi');
        $this->assertContains('Halo semua', $isi);
    }

    /* ══════════════ percakapan langsung ══════════════ */

    public function test_memulai_percakapan_langsung_dengan_rekan_satu_perusahaan(): void
    {
        $perusahaan = $this->perusahaan();
        $a = $this->masuk(perusahaan: $perusahaan);
        $b = User::factory()->create(['company_id' => $perusahaan->id]);

        $this->post('/pesan/mulai', ['user_id' => $b->id])->assertRedirect();

        $this->assertSame(1, Percakapan::where('jenis', 'langsung')->count());
    }

    public function test_memulai_percakapan_langsung_yang_sama_dua_kali_tidak_menggandakan(): void
    {
        $perusahaan = $this->perusahaan();
        $a = $this->masuk(perusahaan: $perusahaan);
        $b = User::factory()->create(['company_id' => $perusahaan->id]);

        $this->post('/pesan/mulai', ['user_id' => $b->id]);
        $this->post('/pesan/mulai', ['user_id' => $b->id]);

        $this->assertSame(1, Percakapan::where('jenis', 'langsung')->count());
    }

    public function test_pengguna_biasa_tidak_dapat_memulai_pesan_lintas_perusahaan(): void
    {
        $a = $this->masuk(perusahaan: $this->perusahaan());
        $b = User::factory()->create(['company_id' => $this->perusahaan()->id]);

        $this->post('/pesan/mulai', ['user_id' => $b->id])->assertForbidden();
    }

    public function test_admin_dapat_memulai_pesan_lintas_perusahaan(): void
    {
        $this->masuk(admin: true);
        $b = User::factory()->create(['company_id' => $this->perusahaan()->id]);

        $this->post('/pesan/mulai', ['user_id' => $b->id])->assertRedirect();
    }

    public function test_tidak_dapat_memulai_percakapan_dengan_diri_sendiri(): void
    {
        $u = $this->masuk();

        $this->post('/pesan/mulai', ['user_id' => $u->id])->assertStatus(422);
    }

    public function test_percakapan_langsung_bersifat_privat(): void
    {
        // Pihak ketiga yang bukan peserta tidak boleh membaca ataupun
        // mengirim pesan ke percakapan orang lain hanya dengan menebak id.
        $perusahaan = $this->perusahaan();
        $a = $this->masuk(perusahaan: $perusahaan);
        $b = User::factory()->create(['company_id' => $perusahaan->id]);
        $this->post('/pesan/mulai', ['user_id' => $b->id]);
        $percakapan = Percakapan::where('jenis', 'langsung')->first();

        $c = User::factory()->create(['company_id' => $perusahaan->id]);
        $this->actingAs($c);

        $this->post("/pesan/{$percakapan->id}", ['isi' => 'menyusup'])->assertForbidden();
    }

    /* ══════════════ belum dibaca ══════════════ */

    public function test_pesan_baru_menaikkan_hitungan_belum_dibaca_penerima(): void
    {
        $perusahaan = $this->perusahaan();
        $a = $this->masuk(perusahaan: $perusahaan);
        $b = User::factory()->create(['company_id' => $perusahaan->id]);
        $this->post('/pesan/mulai', ['user_id' => $b->id]);
        $percakapan = Percakapan::where('jenis', 'langsung')->first();

        $this->post("/pesan/{$percakapan->id}", ['isi' => 'Halo Budi']);

        $this->assertSame(1, $percakapan->belumDibaca($b),
            'Pesan yang belum dilihat penerima harus terhitung.');
        $this->assertSame(0, $percakapan->belumDibaca($a),
            'Pesan milik sendiri tidak boleh terhitung belum dibaca.');

        $this->actingAs($b);
        $this->get('/pesan', ['percakapan' => $percakapan->id])->assertOk();

        $this->assertSame(0, $percakapan->fresh()->belumDibaca($b),
            'Membuka percakapan harus menandainya terbaca.');
    }

    /* ══════════════ penanda pada bilah samping ══════════════ */

    public function test_penanda_pesan_tampak_dari_modul_mana_pun(): void
    {
        // Tanpa penanda, pesan yang masuk saat orang berada di modul lain
        // tidak pernah memanggil siapa pun — fiturnya hanya ditemukan oleh
        // yang kebetulan membuka halaman Pesan.
        $perusahaan = $this->perusahaan();
        $a = $this->masuk(perusahaan: $perusahaan);
        $b = User::factory()->create(['company_id' => $perusahaan->id]);
        $this->post('/pesan/mulai', ['user_id' => $b->id]);
        $percakapan = Percakapan::where('jenis', 'langsung')->first();
        $this->post("/pesan/{$percakapan->id}", ['isi' => 'Halo Budi']);

        $this->actingAs($b);

        $this->assertSame(['pesan.index' => 1], Lencana::semua($b));
    }

    public function test_penanda_tidak_menghitung_pesan_sendiri(): void
    {
        $perusahaan = $this->perusahaan();
        $a = $this->masuk(perusahaan: $perusahaan);
        $b = User::factory()->create(['company_id' => $perusahaan->id]);
        $this->post('/pesan/mulai', ['user_id' => $b->id]);
        $percakapan = Percakapan::where('jenis', 'langsung')->first();
        $this->post("/pesan/{$percakapan->id}", ['isi' => 'Halo Budi']);

        $this->assertSame([], Lencana::semua($a->fresh()),
            'Pengirim tidak boleh melihat penanda atas pesannya sendiri.');
    }

    public function test_penanda_hilang_setelah_percakapan_dibuka(): void
    {
        $perusahaan = $this->perusahaan();
        $a = $this->masuk(perusahaan: $perusahaan);
        $b = User::factory()->create(['company_id' => $perusahaan->id]);
        $this->post('/pesan/mulai', ['user_id' => $b->id]);
        $percakapan = Percakapan::where('jenis', 'langsung')->first();
        $this->post("/pesan/{$percakapan->id}", ['isi' => 'Halo Budi']);

        $this->actingAs($b);
        $this->get('/pesan', ['percakapan' => $percakapan->id])->assertOk();

        $this->assertSame([], Lencana::semua($b->fresh()));
    }

    public function test_tamu_tidak_punya_penanda(): void
    {
        $this->assertSame([], Lencana::semua(null));
    }
}
