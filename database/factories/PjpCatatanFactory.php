<?php

namespace Database\Factories;

use App\Models\Pjp;
use App\Models\PjpCatatan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PjpCatatan>
 */
class PjpCatatanFactory extends Factory
{
    protected $model = PjpCatatan::class;

    public function definition(): array
    {
        return [
            'pjp_id' => Pjp::factory(),
            'isi' => $this->faker->sentence(),
        ];
    }
}
