<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),

            /* Factory membuat PENGGUNA YANG SUDAH ADA, bukan pendaftar baru.
             *
             * Dibiarkan null, tiap akun buatan factory terhitung belum
             * melihat pengenalan situs — sehingga seluruh uji yang
             * membuka halaman ikut membawa langkah pengenalan sembilan
             * setengah kilobita pada propnya, dan uji muatan halaman
             * penilaian jatuh karena hal yang tidak ada hubungannya
             * dengan apa yang sedang diujinya.
             *
             * Uji yang memang menguji pengenalan meng-null-kannya
             * sendiri secara tegas; lihat TurPengenalanTest::baru(). */
            'tur_selesai_pada' => now(),
        ];
    }

    /** Administrator: menjangkau seluruh perusahaan, termasuk audit SMKP. */
    public function admin(): static
    {
        return $this->state(fn () => ['is_admin' => true, 'audit_role' => User::ROLE_AUDITOR]);
    }

    /** Auditor SMKP: mengisi dan mengubah audit perusahaan dalam jangkauannya. */
    public function auditor(?int $companyId = null): static
    {
        return $this->state(fn () => ['audit_role' => User::ROLE_AUDITOR, 'company_id' => $companyId]);
    }

    /** Auditi SMKP: membaca, mengunggah bukti, dan menyampaikan tindak lanjut. */
    public function auditi(?int $companyId = null): static
    {
        return $this->state(fn () => ['audit_role' => User::ROLE_AUDITI, 'company_id' => $companyId]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
