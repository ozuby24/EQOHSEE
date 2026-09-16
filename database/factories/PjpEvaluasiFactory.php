<?php

namespace Database\Factories;

use App\Models\Pjp;
use App\Models\PjpEvaluasi;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PjpEvaluasi>
 */
class PjpEvaluasiFactory extends Factory
{
    protected $model = PjpEvaluasi::class;

    public function definition(): array
    {
        return [
            'pjp_id' => Pjp::factory(),
            'tahun' => now()->year,
            'semester' => 1,
            'skor_teknis' => $this->faker->numberBetween(0, 100),
            'skor_keselamatan_kesehatan' => $this->faker->numberBetween(0, 100),
            'skor_lingkungan' => $this->faker->numberBetween(0, 100),
            'catatan' => null,
        ];
    }
}
