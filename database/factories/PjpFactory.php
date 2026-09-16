<?php

namespace Database\Factories;

use App\Models\Pjp;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pjp>
 */
class PjpFactory extends Factory
{
    protected $model = Pjp::class;

    public function definition(): array
    {
        return [
            'nama_perusahaan' => $this->faker->company(),
            'nib' => $this->faker->numerify('#############'),
            'penanggung_jawab' => $this->faker->name(),
            'alamat' => $this->faker->address(),
            'status' => 'aktif',
            'catatan' => null,
        ];
    }
}
