<?php

namespace Tests\Feature\Auth;

use App\Models\{Company, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Akun baru hanya dapat memakai perusahaan demo.
 *
 * Pendaftaran terbuka untuk siapa saja. Dulu halaman daftarnya memuat
 * SELURUH perusahaan dan menerima perusahaan mana pun, sehingga orang
 * asing dapat menempatkan dirinya di perusahaan pelanggan lalu membaca
 * datanya. Kini akun baru hanya dapat memilih perusahaan bertanda demo;
 * memindahkannya ke perusahaan sungguhan adalah keputusan administrator.
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

    public function test_halaman_daftar_tidak_membocorkan_perusahaan_sungguhan(): void
    {
        $this->get('/register')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->component('Auth/Register')
            ->has('companies', 1)
            ->where('companies.0.name', 'PT Contoh Demo'));

        $this->assertStringNotContainsString('PT Pelanggan Sungguhan', $this->get('/register')->getContent());
    }

    public function test_daftar_ke_perusahaan_demo_berhasil(): void
    {
        $this->post('/register', $this->isian(['company_id' => $this->demo->id]))->assertSessionHasNoErrors();

        $this->assertSame($this->demo->id, (int) User::where('email', 'baru@contoh.id')->value('company_id'));
    }

    /** Daftarnya disaring di layar, tetapi permintaan dapat dikirim tanpa layarnya. */
    public function test_daftar_langsung_ke_perusahaan_sungguhan_ditolak(): void
    {
        $this->post('/register', $this->isian(['company_id' => $this->nyata->id]))
            ->assertSessionHasErrors('company_id');

        $this->assertGuest();
        $this->assertFalse(User::where('email', 'baru@contoh.id')->exists());
    }

    public function test_bila_ada_perusahaan_demo_salah_satunya_wajib_dipilih(): void
    {
        $this->post('/register', $this->isian())->assertSessionHasErrors('company_id');

        $this->assertFalse(User::where('email', 'baru@contoh.id')->exists());
    }

    public function test_tanpa_perusahaan_demo_akun_menunggu_ditempatkan_admin(): void
    {
        $this->demo->update(['demo' => false]);

        $this->post('/register', $this->isian(['company_id' => $this->nyata->id]))
            ->assertSessionHasErrors('company_id');

        $this->post('/register', $this->isian())->assertSessionHasNoErrors();
        $this->assertNull(User::where('email', 'baru@contoh.id')->value('company_id'));
    }

    /** Yang memindahkan ke perusahaan sungguhan adalah administrator. */
    public function test_administrator_memindahkan_akun_baru_ke_perusahaan_sungguhan(): void
    {
        $this->post('/register', $this->isian(['company_id' => $this->demo->id]));
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
