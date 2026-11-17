<?php

namespace Tests\Feature;

use App\Models\{Company, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ke mana orang mendarat sesudah masuk.
 *
 * Pernah salah selama berbulan-bulan tanpa satu pun uji yang gagal:
 * seluruh pengalihan sesudah masuk menuju route('dashboard'), dan nama
 * itu dipegang dasbor PEMBELAJARAN. Akibatnya setiap orang — kepala
 * teknik tambang sekalipun — mendarat di halaman kursusnya sendiri, lalu
 * harus mencari sendiri jalan ke ringkasan situs yang seharusnya ia
 * lihat lebih dulu.
 *
 * Tidak ada galat yang muncul. Halamannya terbuka, isinya benar, dan
 * satu-satunya yang keliru adalah halaman mana yang terbuka.
 */
class PendaratanMasukTest extends TestCase
{
    use RefreshDatabase;

    private const SANDI = 'sandi uji yang panjang';

    private function pengguna(): User
    {
        $c = Company::create(['name' => 'PT Uji Pendaratan', 'code' => 'UPD']);

        return User::factory()->create([
            'email' => 'pengawas@contoh.test', 'password' => self::SANDI,
            'company_id' => $c->id, 'email_verified_at' => now(),
        ]);
    }

    public function test_sesudah_masuk_mendarat_di_ringkasan_situs(): void
    {
        $u = $this->pengguna();

        $this->post('/login', ['email' => $u->email, 'password' => self::SANDI])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->get('/dashboard')->assertOk()
            ->assertInertia(fn ($h) => $h->component('Dasbor/Halaman')->etc());
    }

    /**
     * /dashboard adalah ringkasan situs, BUKAN dasbor pembelajaran.
     *
     * Dijaga lewat komponen halamannya, bukan lewat isinya: isi dapat
     * berubah, sedangkan pertanyaan "halaman mana yang dibuka nama rute
     * ini" tidak boleh berubah tanpa ada yang memutuskannya.
     */
    public function test_rute_dashboard_menunjuk_ringkasan_situs(): void
    {
        $this->actingAs($this->pengguna());

        $this->get(route('dashboard'))->assertOk()
            ->assertInertia(fn ($h) => $h->component('Dasbor/Halaman')->etc());
    }

    public function test_dasbor_pembelajaran_pindah_ke_lms(): void
    {
        $this->actingAs($this->pengguna());

        $this->get(route('lms.dasbor'))->assertOk()
            ->assertInertia(fn ($h) => $h->component('Dashboard')->etc());
    }

    /**
     * Alamat lama tetap hidup.
     *
     * /dasbor sudah beredar — ditandai di peramban, ditempel di grup
     * WhatsApp, tercetak pada tangkapan layar yang sudah dikirim.
     * Menghapusnya mengubah tautan yang pernah dibagikan menjadi halaman
     * galat, dan yang membukanya menyimpulkan aplikasinya rusak, bukan
     * alamatnya yang pindah.
     */
    public function test_alamat_lama_dasbor_masih_mengarah_ke_tempat_yang_benar(): void
    {
        $this->actingAs($this->pengguna());

        $this->get('/dasbor')->assertRedirect('/dashboard');
    }

    /**
     * Seluruh pintu sesudah masuk menuju tempat yang sama.
     *
     * Bukan hanya halaman masuk: verifikasi surel, konfirmasi sandi, dan
     * kode dua faktor semuanya memulangkan orang ke suatu tempat, dan
     * satu di antaranya yang tertinggal berarti satu jalan masuk yang
     * masih mendaratkan orang di halaman yang salah.
     */
    public function test_tidak_ada_pengalihan_yang_tertinggal_ke_dasbor_pembelajaran(): void
    {
        $berkas = glob(app_path('Http/Controllers/Auth/*.php'));
        $langgar = [];

        foreach ($berkas as $f) {
            $isi = file_get_contents($f);

            if (str_contains($isi, "route('lms.dasbor'")) {
                $langgar[] = basename($f);
            }
        }

        $this->assertSame([], $langgar,
            'Pengalihan sesudah masuk masih menuju dasbor pembelajaran: '
            .implode(', ', $langgar).'. Yang dibuka orang pertama kali harus '
            .'ringkasan situsnya.');
    }
}
