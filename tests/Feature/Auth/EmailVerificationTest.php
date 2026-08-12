<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\KodeVerifikasiEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Verifikasi email dengan kode enam angka.
 *
 * Menggantikan alur tautan bawaan. Tautan verifikasi hanya bekerja bila
 * surelnya dibuka di peramban yang sama dengan tempat mendaftar — surel
 * kerja sering dibuka di ponsel lain atau di peramban dalaman sebuah
 * aplikasi, dan sesi di sana kosong.
 */
class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    private function belumTerverifikasi(): User
    {
        return User::factory()->create(['email_verified_at' => null]);
    }

    public function test_pendaftaran_mengirim_kode_dan_mengarah_ke_verifikasi(): void
    {
        Notification::fake();

        $this->post('/register', [
            'name' => 'Budi Santoso', 'email' => 'budi@contoh.test',
            'position' => 'Safety Officer',
            'password' => 'Rahasia123!', 'password_confirmation' => 'Rahasia123!',
        ])->assertRedirect(route('verification.notice', absolute: false));

        $u = User::where('email', 'budi@contoh.test')->firstOrFail();

        $this->assertNull($u->email_verified_at);
        Notification::assertSentTo($u, KodeVerifikasiEmail::class);
    }

    public function test_kode_yang_benar_memverifikasi_email(): void
    {
        $u = $this->belumTerverifikasi();
        $kode = $u->buatKodeVerifikasi();

        $this->actingAs($u)->post('/verify-email', ['kode' => $kode])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertNotNull($u->fresh()->email_verified_at);
    }

    public function test_kode_tidak_disimpan_apa_adanya(): void
    {
        // Enam angka hanya sejuta kemungkinan; kode yang tersimpan mentah
        // dapat dipakai siapa pun yang sempat membaca basis datanya.
        $u = $this->belumTerverifikasi();
        $kode = $u->buatKodeVerifikasi();

        $this->assertNotSame($kode, $u->fresh()->kode_verifikasi);
    }

    public function test_kode_salah_ditolak_dan_dihitung(): void
    {
        $u = $this->belumTerverifikasi();
        $u->buatKodeVerifikasi();

        $this->actingAs($u)->post('/verify-email', ['kode' => '000000'])
            ->assertSessionHasErrors('kode');

        $this->assertNull($u->fresh()->email_verified_at);
        $this->assertSame(1, $u->fresh()->kode_verifikasi_percobaan);
    }

    public function test_kode_hangus_setelah_terlalu_banyak_percobaan(): void
    {
        /* Tanpa batas percobaan, sejuta kemungkinan dapat ditebak jauh
           lebih cepat daripada kodenya kedaluwarsa — masa berlaku saja
           bukan pengaman. */
        $u = $this->belumTerverifikasi();
        $kode = $u->buatKodeVerifikasi();

        foreach (range(1, User::KODE_MAKS_SALAH) as $i) {
            $this->actingAs($u)->post('/verify-email', ['kode' => '000000']);
        }

        // Kode yang BENAR pun kini ditolak sampai diminta yang baru.
        $this->actingAs($u->fresh())->post('/verify-email', ['kode' => $kode])
            ->assertSessionHasErrors('kode');

        $this->assertNull($u->fresh()->email_verified_at);
    }

    public function test_kode_kedaluwarsa_ditolak(): void
    {
        $u = $this->belumTerverifikasi();
        $kode = $u->buatKodeVerifikasi();

        $u->forceFill(['kode_verifikasi_at' => now()->subMinutes(User::KODE_BERLAKU + 1)])->save();

        $this->actingAs($u->fresh())->post('/verify-email', ['kode' => $kode])
            ->assertSessionHasErrors('kode');

        $this->assertNull($u->fresh()->email_verified_at);
    }

    public function test_kirim_ulang_dijeda_di_server(): void
    {
        /* Jeda tidak boleh hanya berupa tombol yang dimatikan: tombol mati
           hanya menghalangi orang yang memakai halamannya apa adanya. */
        $u = $this->belumTerverifikasi();
        $u->buatKodeVerifikasi();

        $this->actingAs($u->fresh())->post('/email/verification-notification')
            ->assertSessionHasErrors('kode');
    }

    public function test_belum_terverifikasi_tidak_dapat_membuka_aplikasi(): void
    {
        $u = $this->belumTerverifikasi();

        $this->actingAs($u)->get('/dashboard')
            ->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_sudah_terverifikasi_tidak_tertahan_di_halaman_verifikasi(): void
    {
        $u = User::factory()->create();

        $this->actingAs($u)->get('/verify-email')
            ->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_pengguna_lama_tidak_terkunci_saat_verifikasi_diberlakukan(): void
    {
        /* Akun yang dibuat sebelum verifikasi ada ditandai terverifikasi
           oleh migrasinya. Tanpa itu, pemasangan fitur ini mengunci
           seluruh pengguna lama dari aplikasinya sendiri — kegagalan yang
           baru ketahuan setelah orang tidak bisa masuk. */
        $this->assertSame(0, User::whereNull('email_verified_at')->count());
    }
}
