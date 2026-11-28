<?php

namespace Tests\Feature\Auth;

use App\Models\{Company, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Akun baru otomatis masuk perusahaan demo.
 *
 * Pendaftaran terbuka untuk siapa saja. Dulu halaman daftarnya memuat
 * SELURUH perusahaan dan menerima perusahaan mana pun, sehingga orang
 * asing dapat menempatkan dirinya di perusahaan pelanggan lalu membaca
 * datanya. Kemudian pilihannya dibatasi ke perusahaan demo; kini
 * pilihannya dihapus sama sekali (permintaan pemilik): server yang
 * menempatkan akun baru di perusahaan demo, dan isian company_id dari
 * permintaan mana pun diabaikan. Memindahkannya ke perusahaan sungguhan
 * adalah keputusan administrator.
 */
class PendaftaranPerusahaanDemoTest extends TestCase
{
    use RefreshDatabase;

    private Company $demo;
    private Company $nyata;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

        $this->demo  = Company::create(['name' => 'PT Contoh Demo', 'demo' => true]);
        $this->nyata = Company::create(['name' => 'PT Pelanggan Sungguhan', 'demo' => false]);
    }

    private function isian(array $ubah = []): array
    {
        return $ubah + [
            'name' => 'Pendaftar Baru',
            'email' => 'baru@contoh.id',
            'password' => 'sandi uji yang panjang',
            'password_confirmation' => 'sandi uji yang panjang',
            'position' => \App\Support\Hazard::JABATAN[0],
        ];
    }

    public function test_halaman_daftar_tanpa_pilihan_perusahaan_dan_tanpa_nama_perusahaan(): void
    {
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Auth/Register')
            ->missing('companies')
            ->where('adaDemo', true));

        $isi = $this->get('/register')->getContent();
        $this->assertStringNotContainsString('PT Pelanggan Sungguhan', $isi);
        $this->assertStringNotContainsString('PT Contoh Demo', $isi,
            'Nama perusahaan dikirim ke pengunjung yang belum masuk, padahal tidak ada yang dipilih.');

        $halaman = file_get_contents(resource_path('js/Pages/Auth/Register.vue'));
        $this->assertStringNotContainsString('company_id', $halaman, 'Formulir Daftar kembali memuat pilihan perusahaan.');
    }

    public function test_akun_baru_otomatis_masuk_perusahaan_demo(): void
    {
        $this->post('/register', $this->isian())->assertSessionHasNoErrors();

        $this->assertSame($this->demo->id, (int) User::where('email', 'baru@contoh.id')->value('company_id'));
    }

    /** Pilihannya tidak ada di layar, tetapi permintaan dapat dikirim tanpa layarnya. */
    public function test_perusahaan_dari_permintaan_buatan_diabaikan(): void
    {
        $this->post('/register', $this->isian(['company_id' => $this->nyata->id]))->assertSessionHasNoErrors();

        $this->assertSame($this->demo->id, (int) User::where('email', 'baru@contoh.id')->value('company_id'),
            'Akun baru menempatkan dirinya di perusahaan sungguhan.');
    }

    /** Beberapa perusahaan demo: PT DEMO (kode DEMO) bila ada, selain itu yang tertua. */
    public function test_perusahaan_demo_utama_didahulukan(): void
    {
        $utama = Company::create(['name' => 'PT DEMO', 'code' => 'DEMO', 'demo' => true]);
        Company::create(['name' => 'PT Andalas Contoh', 'code' => 'AND', 'demo' => true]);

        $this->post('/register', $this->isian())->assertSessionHasNoErrors();
        $this->assertSame($utama->id, (int) User::where('email', 'baru@contoh.id')->value('company_id'));

        $utama->update(['demo' => false]);
        auth()->logout();
        $this->post('/register', $this->isian(['email' => 'kedua@contoh.id']))->assertSessionHasNoErrors();
        $this->assertSame($this->demo->id, (int) User::where('email', 'kedua@contoh.id')->value('company_id'),
            'Tanpa PT DEMO, akun baru tidak masuk perusahaan demo tertua.');
    }

    public function test_tanpa_perusahaan_demo_akun_menunggu_ditempatkan_admin(): void
    {
        $this->demo->update(['demo' => false]);

        $this->get('/register')->assertInertia(fn (Assert $p) => $p->where('adaDemo', false));

        $this->post('/register', $this->isian(['company_id' => $this->nyata->id]))->assertSessionHasNoErrors();
        $this->assertNull(User::where('email', 'baru@contoh.id')->value('company_id'));
    }

    /** Yang memindahkan ke perusahaan sungguhan adalah administrator. */
    public function test_administrator_memindahkan_akun_baru_ke_perusahaan_sungguhan(): void
    {
        $this->post('/register', $this->isian());
        $baru = User::where('email', 'baru@contoh.id')->firstOrFail();
        auth()->logout();

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put(route('admin.users.update', $baru), [
            'name' => $baru->name, 'email' => $baru->email, 'company_id' => $this->nyata->id,
            'position' => $baru->position, 'active' => true,
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->nyata->id, (int) $baru->refresh()->company_id);
    }
}
