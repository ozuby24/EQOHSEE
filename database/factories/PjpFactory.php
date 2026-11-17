<?php

namespace Database\Factories;

use App\Models\Pjp;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pjp> */
class PjpFactory extends Factory
{
    protected $model = Pjp::class;

    public function definition(): array
    {
        return [
            /*
             * Tanpa perusahaan. Batas per perusahaan memperlakukan NULL
             * sebagai "belum dimiliki siapa pun" dan tetap terlihat oleh
             * semua orang — itu yang dibutuhkan uji aturan skor, yang
             * tidak mempersoalkan siapa pemantaunya. Uji yang memang
             * menguji batasnya menyebut company_id secara tegas.
             */
            'company_id'       => null,
            'nama_perusahaan'  => $this->faker->company(),
            'nib'              => $this->faker->numerify('#############'),
            'penanggung_jawab' => $this->faker->name(),
            'alamat'           => $this->faker->address(),
            'status'           => 'aktif',
            'catatan'          => null,
        ];
    }
}
