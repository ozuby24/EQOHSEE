<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kunjungan Inertia yang mendarat di halaman Blade.
 *
 * Inertia tidak mundur sendiri ke navigasi peramban ketika tanggapannya
 * bukan Inertia — HTML utuh itu ditampilkan mentah di dalam bingkai
 * galat, sehingga halaman tujuan tampak sebagai jendela rusak di atas
 * halaman yang baru ditinggalkan. Aplikasi ini setengah Blade dan
 * setengah Inertia, jadi keadaan itu bukan kasus langka melainkan
 * konsekuensi biasa dari setiap pengalihan lintas keduanya.
 */
class TanggapanInertiaTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_blade_menjawab_kunjungan_inertia_dengan_navigasi_penuh(): void
    {
        // Dashboard dirender Blade. Dikunjungi sebagai Inertia, jawabannya
        // harus berupa perintah memuat alamat itu secara penuh — bukan
        // HTML yang akan ditampilkan mentah di dalam bingkai.
        $this->actingAs(User::factory()->create());

        $this->withHeader('X-Inertia', 'true')
            ->get('/dashboard')
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url('/dashboard'));
    }

    public function test_halaman_inertia_tidak_ikut_terbungkus(): void
    {
        // Yang sudah Inertia harus lewat apa adanya; membungkusnya ulang
        // akan membuat setiap perpindahan antar halaman Vue berubah
        // menjadi muat ulang penuh.
        $this->actingAs(User::factory()->create());

        /* Versi aset ikut dikirim. Tanpa itu Inertia sendiri menjawab 409
           karena versinya dianggap tidak cocok — bukan oleh middleware
           yang sedang diuji, tetapi tetap membuat uji ini gagal. */
        $versi = (new \App\Http\Middleware\HandleInertiaRequests)->version(request());

        $this->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Version', (string) $versi)
            ->get('/personalia')
            ->assertOk()
            ->assertHeader('X-Inertia', 'true');
    }

    public function test_kunjungan_biasa_ke_halaman_blade_tetap_html(): void
    {
        // Tanpa tanda Inertia, tidak ada yang perlu diselamatkan.
        $this->actingAs(User::factory()->create());

        $this->get('/dashboard')->assertOk()->assertHeaderMissing('X-Inertia-Location');
    }

    public function test_rantai_pengalihan_menuju_blade_ikut_terselamatkan(): void
    {
        /* Pengalihan sengaja dilewatkan: klien Inertia mengikutinya
           sendiri lewat XHR, dan yang menentukan nasibnya adalah tanggapan
           di UJUNG rantai. Uji ini menyusuri rantai itu seperti kliennya,
           untuk memastikan ujungnya memang tertangkap — sebab kalau tidak,
           satu-satunya yang terlihat orang adalah jendela rusak. */
        $this->actingAs(User::factory()->create());

        // Halaman depan dirender Blade — tujuan pengalihan setelah keluar.
        $this->withHeader('X-Inertia', 'true')
            ->get('/')
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', url('/'));
    }
}
