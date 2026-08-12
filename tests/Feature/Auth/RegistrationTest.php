<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            // Jabatan wajib diisi sejak pendaftaran ikut mengumpulkan data man
            // power — dipakai untuk menghitung target KPI Hazard & Inspeksi.
            'position' => \App\Support\Hazard::JABATAN[0],
        ]);

        $this->assertAuthenticated();
        // Pendaftaran kini berakhir di verifikasi, bukan langsung masuk.
        $response->assertRedirect(route('verification.notice', absolute: false));
    }
}
