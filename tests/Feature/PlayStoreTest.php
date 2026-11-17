<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Artisan, Hash, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Hal-hal yang diperiksa Google Play dari LUAR aplikasi.
 *
 * Peninjau membuka alamat-alamat ini dari perangkat tanpa akun. Halaman
 * yang mengalihkan ke halaman masuk, kebijakan yang berbeda dari perilaku
 * aplikasinya, atau akun peninjau yang tidak dapat masuk — masing-masing
 * berujung penolakan, dan tidak satu pun menimbulkan galat di server.
 */
class PlayStoreTest extends TestCase
{
    use RefreshDatabase;

    public static function halamanHapus(): array
    {
        return [['/hapus-akun'], ['/delete-account']];
    }

    #[DataProvider('halamanHapus')]
    public function test_halaman_hapus_akun_terbuka_tanpa_masuk(string $alamat): void
    {
        $r = $this->get($alamat)->assertStatus(200);

        $r->assertSee(config('hukum.surel'), false);
        $r->assertSee('30', false);
    }

    public function test_halaman_hapus_akun_menyebut_cara_data_terhapus_dan_yang_disimpan(): void
    {
        $this->get('/hapus-akun')
            ->assertSee('Profil → Akun &amp; kata sandi', false)
            ->assertSee('Data yang dihapus', false)
            ->assertSee('Data yang tetap disimpan', false)
            ->assertSee('/delete-account', false);

        $this->get('/delete-account')->assertSee('/hapus-akun', false);
    }

    public function test_kebijakan_privasi_sesuai_perilaku_aplikasi(): void
    {
        foreach (['/kebijakan-privasi' => ['Lokasi laporan bahaya', 'tidak meminta izin kamera', '/hapus-akun', 'Biometrik'],
                  '/privacy-policy'   => ['Hazard report location', 'does not request camera', '/delete-account', 'Biometric']] as $alamat => $wajib) {
            $r = $this->get($alamat)->assertOk();
            foreach ($wajib as $teks) $r->assertSee($teks, false);
        }

        /* Kalimat lama ini keliru sejak lapor bahaya mencatat GPS. Kebijakan
           yang menyangkal izin yang diminta aplikasinya adalah alasan
           penangguhan, bukan sekadar penolakan. */
        $this->get('/kebijakan-privasi')->assertDontSee('tidak meminta izin lokasi sama sekali', false);
        $this->get('/privacy-policy')->assertDontSee('does not request location permission at all', false);
    }

    public function test_profil_lapangan_menautkan_privasi_dan_hapus_akun(): void
    {
        $c = Company::create(['name' => 'PT Uji Play', 'code' => 'UP1']);
        $this->actingAs(User::factory()->create(['company_id' => $c->id]));

        $this->get('/lapangan/profil')->assertInertia(fn ($p) => $p
            ->where('tautan.privasi', route('hukum.privasi'))
            ->where('tautan.hapusAkun', route('profile.edit').'#hapus-akun'));
    }

    public function test_akun_peninjau_dibuat_dengan_sandi_acak_dan_bukan_admin(): void
    {
        $c = Company::create(['name' => 'PT Citra Dayak Indah', 'code' => 'CDI']);

        Artisan::call('peninjau:pasang');
        $keluaran = Artisan::output();

        $u = User::where('email', 'peninjau.play@eqohsee.id')->firstOrFail();
        $this->assertFalse((bool) $u->is_admin, 'Peninjau tidak boleh melihat data seluruh perusahaan.');
        $this->assertSame($c->id, $u->company_id);
        $this->assertNotNull($u->email_verified_at, 'Akun belum terverifikasi tertahan di layar verifikasi surel.');

        preg_match('/Sandi\s*:\s*(\S+)/', $keluaran, $m);
        $this->assertNotEmpty($m[1] ?? null, 'Sandi harus dicetak sekali.');
        $this->assertGreaterThanOrEqual(20, strlen($m[1]));
        $this->assertTrue(Hash::check($m[1], $u->password));

        /* Menjalankan ulang mengganti sandinya. */
        Artisan::call('peninjau:pasang');
        preg_match('/Sandi\s*:\s*(\S+)/', Artisan::output(), $m2);
        $this->assertNotSame($m[1], $m2[1]);
        $this->assertFalse(Hash::check($m[1], $u->fresh()->password));

        /* Sesudah peninjauan: dinonaktifkan dan sandinya diacak. */
        Artisan::call('peninjau:pasang', ['--nonaktif' => true]);
        $this->assertFalse(Hash::check($m2[1], $u->fresh()->password));
        $this->assertFalse((bool) $u->fresh()->active);
    }

    public function test_akun_peninjau_menolak_perusahaan_yang_tidak_ada(): void
    {
        $this->assertSame(1, Artisan::call('peninjau:pasang', ['--perusahaan' => 'TIDAKADA']));
        $this->assertSame(0, User::where('email', 'peninjau.play@eqohsee.id')->count());
    }

    public function test_hapus_akun_membuang_foto_profil(): void
    {
        Storage::fake('public');
        $foto = UploadedFile::fake()->image('saya.jpg')->store('avatar', 'public');
        $u = User::factory()->create(['avatar' => $foto, 'password' => Hash::make('rahasia123')]);
        Storage::disk('public')->assertExists($foto);

        $this->actingAs($u)->delete('/profile', ['password' => 'rahasia123'])->assertRedirect('/');

        $this->assertNull(User::find($u->id));
        Storage::disk('public')->assertMissing($foto);
    }
}
