<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_can_be_updated(): void
    {
        /* Daftar bocoran dipalsukan: uji ini menguji alur penggantian
           sandinya, bukan pemeriksaan HIBP — yang diuji tersendiri di
           AturanSandiTest. Tanpa ini, tiap jalannya menembak layanan
           luar yang sungguhan. */
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'sandi uji yang panjang',
                'password_confirmation' => 'sandi uji yang panjang',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertTrue(Hash::check('sandi uji yang panjang', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->put('/password', [
                'current_password' => 'wrong-password',
                'password' => 'sandi uji yang panjang',
                'password_confirmation' => 'sandi uji yang panjang',
            ]);

        $response
            ->assertSessionHasErrorsIn('updatePassword', 'current_password')
            ->assertRedirect('/profile');
    }
}
