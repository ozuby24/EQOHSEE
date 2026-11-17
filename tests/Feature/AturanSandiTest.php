<?php

namespace Tests\Feature;

use App\Support\AturanSandi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Tests\TestCase;

/**
 * Aturan sandi: panjang minimum dan pemeriksaan daftar bocoran.
 *
 * Ini menutup celah yang tidak disentuh Turnstile maupun pembatas laju.
 * Keduanya menahan orang yang menebak BERKALI-KALI; tidak satu pun
 * menahan orang yang menebak SEKALI dengan tebakan yang benar, karena
 * sandinya sudah ia punya dari kebocoran situs lain.
 */
class AturanSandiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Membuat balasan HIBP palsu yang menyatakan sandi ini bocor.
     *
     * HIBP memulangkan daftar "SIRIP:JUMLAH" untuk semua sandi yang
     * sirip SHA-1-nya berawalan sama. Dihitung sungguhan di sini, bukan
     * ditulis tetap, supaya tiruannya berbentuk sama persis dengan
     * aslinya — termasuk baris lain yang tidak cocok, yang memang selalu
     * ikut dan yang harus diabaikan pemeriksanya.
     */
    private function bocor(string $sandi): void
    {
        $sirip = strtoupper(sha1($sandi));

        Http::fake(['api.pwnedpasswords.com/*' => Http::response(
            "0018A45C4D1DEF81644B54AB7F969B88D65:1\n"
            .substr($sirip, 5).":37359\n"
            ."00D4F6E8FA6EECAD2A3AA415EEC418D38EC:2"
        )]);
    }

    private function aman(): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response(
            "0018A45C4D1DEF81644B54AB7F969B88D65:1\n"
            ."00D4F6E8FA6EECAD2A3AA415EEC418D38EC:2"
        )]);
    }

    private function nilai(string $sandi): \Illuminate\Validation\Validator
    {
        return Validator::make(
            ['password' => $sandi],
            ['password' => [Password::defaults()]],
            AturanSandi::pesan(),
        );
    }

    /* ═══════════ panjang ═══════════ */

    /**
     * "password" diterima sebelum perubahan ini. Benar-benar diterima.
     */
    public function test_sandi_pendek_ditolak(): void
    {
        $this->aman();

        foreach (['password', 'rahasia1', 'Sandi123!', str_repeat('a', AturanSandi::MINIMAL - 1)] as $s) {
            $this->assertTrue($this->nilai($s)->fails(), "Sandi '{$s}' seharusnya ditolak.");
        }
    }

    /**
     * Tidak ada tuntutan huruf besar, angka, atau tanda baca.
     *
     * Kalimat pendek biasa harus lolos. Kalau suatu hari aturan susunan
     * ikut dipasang, uji ini yang jatuh lebih dulu — dan itu memang
     * maksudnya: aturan susunan menghasilkan "Sandi123!" yang justru
     * paling awal dicoba mesin penebak.
     */
    public function test_kalimat_biasa_tanpa_angka_diterima(): void
    {
        $this->aman();

        $this->assertFalse($this->nilai('kopi pagi di tambang')->fails(),
            'Kalimat panjang tanpa angka ditolak; ada aturan susunan yang ikut terpasang.');
    }

    /* ═══════════ daftar bocoran ═══════════ */

    public function test_sandi_yang_pernah_bocor_ditolak(): void
    {
        $this->bocor('kopi pagi di tambang');

        $v = $this->nilai('kopi pagi di tambang');

        $this->assertTrue($v->fails(),
            'Sandi yang ada di daftar bocoran diterima; pemeriksaan HIBP tidak berjalan.');

        $this->assertStringContainsString('kebocoran data', $v->errors()->first('password'));
    }

    /**
     * Sandinya TIDAK PERNAH meninggalkan server ini.
     *
     * Yang dikirim hanya lima huruf pertama sirip SHA-1-nya. Ini
     * penjagaan yang paling perlu ada di berkas ini: kalau suatu hari
     * pemeriksanya diganti dengan layanan lain yang menerima sandinya
     * mentah-mentah, tidak ada satu pun gejala yang terlihat — situsnya
     * tetap bekerja persis sama, dan yang berubah hanya ke mana sandi
     * setiap pengguna dikirimkan.
     */
    public function test_sandinya_tidak_pernah_dikirim_keluar(): void
    {
        $this->aman();

        $sandi = 'kopi pagi di tambang';
        $sirip = strtoupper(sha1($sandi));

        $this->nilai($sandi)->fails();

        Http::assertSent(function ($permintaan) use ($sandi, $sirip) {
            $seluruhnya = $permintaan->url().' '.$permintaan->body();

            $this->assertStringNotContainsString($sandi, $seluruhnya,
                'Sandi mentah ikut terkirim ke layanan luar.');

            $this->assertStringNotContainsString($sirip, $seluruhnya,
                'Sirip SHA-1 LENGKAP ikut terkirim; yang boleh keluar hanya lima huruf pertamanya.');

            $this->assertStringContainsString(substr($sirip, 0, 5), $permintaan->url());

            return true;
        });
    }

    /**
     * HIBP mati tidak boleh mengunci pendaftaran.
     *
     * Sikap yang sama dengan Turnstile pada 'lolos', atas alasan yang
     * sama: gangguan pada layanan pihak ketiga tidak boleh berubah
     * menjadi situs yang tidak dapat dipakai mendaftar.
     */
    public function test_hibp_mati_tetap_meloloskan(): void
    {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('', 503)]);

        $this->assertFalse($this->nilai('kopi pagi di tambang')->fails(),
            'HIBP yang sedang mati menolak sandi yang sah.');
    }

    /* ═══════════ terpasang di ketiga pintunya ═══════════ */

    public function test_pendaftaran_menolak_sandi_bocoran(): void
    {
        $this->bocor('kopi pagi di tambang');

        $this->post('/register', [
            'name'                  => 'Pekerja Baru',
            'email'                 => 'pekerja.baru@contoh.test',
            'password'              => 'kopi pagi di tambang',
            'password_confirmation' => 'kopi pagi di tambang',
            'position'              => \App\Support\Hazard::JABATAN[0],
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'pekerja.baru@contoh.test']);
    }

    /**
     * Pesannya berbahasa Indonesia, seperti seluruh formulirnya.
     *
     * Bukan kerapian belaka: pesan bawaan Laravel berbunyi "The given
     * password has appeared in a data leak", dan pada formulir yang
     * seluruhnya berbahasa Indonesia ia berakhir sebagai telepon ke
     * administrator, bukan sebagai sandi yang diganti.
     */
    public function test_pesannya_berbahasa_indonesia(): void
    {
        $this->bocor('kopi pagi di tambang');

        $galat = $this->nilai('kopi pagi di tambang')->errors()->first('password');
        $this->assertStringNotContainsString('The given', $galat);

        $this->aman();

        $pendek = $this->nilai('pendek')->errors()->first('password');
        $this->assertStringNotContainsString('must be at least', $pendek);
        $this->assertStringContainsString((string) AturanSandi::MINIMAL, $pendek);
    }

    /**
     * Angka minimalnya disebut dari satu tempat.
     *
     * Aturan dan pesannya yang menyebut angka masing-masing akan berbeda
     * pada suatu hari, dan yang membacanya diberi tahu batas yang salah
     * oleh formulir yang menolaknya dengan batas yang lain.
     */
    public function test_angka_minimal_tidak_ditulis_dua_kali(): void
    {
        $provider = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));

        $this->assertStringContainsString('Password::min(AturanSandi::MINIMAL)', $provider,
            'AppServiceProvider menulis angka minimalnya sendiri, lepas dari pesannya.');
    }
}
