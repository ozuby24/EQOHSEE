<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_keluar_dari_halaman_inertia_memerintahkan_navigasi_penuh(): void
    {
        /* Halaman depan dirender Blade, bukan Inertia. Ketika keluar
           ditekan dari halaman Vue, tombolnya melakukan kunjungan Inertia,
           dan Inertia yang menerima HTML utuh TIDAK jatuh sendiri ke
           navigasi peramban — ia menampilkan HTML itu mentah di dalam
           bingkai galat, sehingga halaman depan tampak sebagai jendela
           rusak di atas halaman yang baru ditinggalkan. */
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeader('X-Inertia', 'true')
            ->post('/logout')
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', '/');

        $this->assertGuest();
    }

    public function test_keluar_dari_halaman_blade_tetap_pengalihan_biasa(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/logout')->assertRedirect('/');

        $this->assertGuest();
    }
}
