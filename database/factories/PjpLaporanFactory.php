<?php

namespace Database\Factories;

use App\Models\Pjp;
use App\Models\PjpLaporan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PjpLaporan>
 */
class PjpLaporanFactory extends Factory
{
    protected $model = PjpLaporan::class;

    public function definition(): array
    {
        return [
            'pjp_id' => Pjp::factory(),
            'jenis' => 'laporan_bulanan',
            'periode' => null,
            'file_path' => 'pjp-laporan/1/'.$this->faker->uuid().'.pdf',
            'file_name' => $this->faker->word().'.pdf',
            'file_size' => $this->faker->numberBetween(1000, 500000),
            'catatan' => null,
            'kesesuaian_isi' => null,
        ];
    }
}
