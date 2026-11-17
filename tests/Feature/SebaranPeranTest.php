<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Donat "Sebaran Peran" di Pusat Kendali Sistem.
 *
 * Satu orang satu peran, dan jumlahnya sama dengan seluruh pengguna.
 * Sebelumnya tiap peran dihitung dengan kuerinya sendiri: administrator
 * yang juga KTT terhitung dua kali, akun nonaktif terhitung lagi di
 * perannya, dan peserta ber-lms_role selain 'trainee' tidak masuk ke
 * kategori mana pun — donatnya menyebut "4 pengguna" di samping kartu
 * yang menyebut 18.
 */
class SebaranPeranTest extends TestCase
{
    use RefreshDatabase;

    private function peran(): array
    {
        $props = $this->get(route('admin.system'))->assertOk()->viewData('page')['props'];

        return collect($props['peran'])->pluck('jumlah', 'label')->all();
    }

    public function test_jumlah_seluruh_peran_sama_dengan_jumlah_pengguna(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'lms_role' => 'ktt']);
        User::factory()->count(3)->create(['lms_role' => 'trainee']);
        User::factory()->create(['lms_role' => null]);
        User::factory()->create(['lms_role' => 'trainer']);
        User::factory()->create(['lms_role' => 'ktt']);
        User::factory()->create(['lms_role' => 'trainee', 'active' => false]);

        $this->actingAs($admin);
        $peran = $this->peran();

        $this->assertSame(User::count(), array_sum($peran));
        $this->assertSame(1, $peran['Administrator'], 'Admin yang juga KTT dihitung sekali, sebagai administrator.');
        $this->assertSame(1, $peran['KTT']);
        $this->assertSame(1, $peran['Trainer']);
        $this->assertSame(4, $peran['Peserta'], 'Trainee dan yang belum berperan sama-sama peserta.');
        $this->assertSame(1, $peran['Nonaktif']);
    }

    public function test_peran_lms_yang_tidak_dikenal_tetap_terhitung_sebagai_peserta(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        User::factory()->count(2)->create(['lms_role' => 'peserta']);

        $this->actingAs($admin);
        $peran = $this->peran();

        $this->assertSame(2, $peran['Peserta']);
        $this->assertSame(User::count(), array_sum($peran));
    }
}
