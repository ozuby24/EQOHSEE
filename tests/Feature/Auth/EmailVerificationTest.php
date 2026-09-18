<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\KodeVerifikasiEmail;
use Illuminate\Support\Facades\Http;
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

        /* Daftar bocoran dipalsukan: uji ini menguji surel verifikasinya,
           bukan pemeriksaan HIBP — yang diuji tersendiri di
           AturanSandiTest. Tanpa ini, tiap jalannya menembak layanan
           luar yang sungguhan. */
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

        $this->post('/register', [
            'name' => 'Budi Santoso', 'email' => 'budi@contoh.test',
            'position' => 'Safety Officer',
            'password' => 'sandi uji yang panjang', 'password_confirmation' => 'sandi uji yang panjang',
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
            ->assertRedirect(route('login', absolute: false));

        $this->assertNotNull($u->fresh()->email_verified_at);
    }

    public function test_kode_yang_benar_tidak_memasukkan_siapa_pun(): void
    {
        /* Terverifikasi BUKAN berarti masuk.
         *
         * Yang dibuktikan orangnya dengan kode enam angka hanyalah bahwa
         * ia memegang kotak surat itu. Sandi yang dipilihnya saat
         * mendaftar tidak pernah diminta sekali pun, jadi tidak pernah
         * teruji — dan kotak surat yang tertinggal terbuka di ponsel
         * bersama menjadi cukup untuk masuk. */
        $u = $this->belumTerverifikasi();
        $kode = $u->buatKodeVerifikasi();

        $this->actingAs($u)->post('/verify-email', ['kode' => $kode]);

        $this->assertGuest();
    }

    public function test_sesi_sesudah_verifikasi_tidak_dapat_membuka_dasbor(): void
    {
        /* Penjagaan yang sesungguhnya, bukan sekadar assertGuest().
         *
         * Pengalihan ke halaman masuk TANPA menutup sesinya akan lolos
         * dari pemeriksaan pengalihan mana pun, dan tetap meninggalkan
         * orangnya masuk di belakang halaman itu: mengetik alamat dasbor
         * langsung membukanya. Yang diuji di sini adalah akibatnya, bukan
         * bentuk jawabannya. */
        $u = $this->belumTerverifikasi();
        $kode = $u->buatKodeVerifikasi();

        $this->actingAs($u)->post('/verify-email', ['kode' => $kode]);

        $this->get('/dashboard')->assertRedirect(route('login', absolute: false));
    }

    public function test_halaman_masuk_mengabarkan_verifikasinya_berhasil(): void
    {
        /* Sesi ditutup, dan pesan kilat biasanya ikut terbuang bersama
           isinya. Tanpa pesan ini halaman masuk menyambut persis seperti
           kalau kodenya salah — pada saat orangnya justru paling perlu
           tahu bahwa ia berhasil. */
        $u = $this->belumTerverifikasi();
        $kode = $u->buatKodeVerifikasi();

        $this->actingAs($u)->post('/verify-email', ['kode' => $kode])
            ->assertSessionHas('sukses');
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
