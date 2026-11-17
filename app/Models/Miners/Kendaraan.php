<?php

namespace App\Models\Miners;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Golongan kendaraan, beserta kelas SIMPOL yang dituntutnya.
 *
 * `kelas_simpol` dan `wajib_sio` disimpan sebagai DATA, bukan sebagai
 * daftar di dalam kode. SOP 001-SPM-007 mensyaratkan kecocokan unit
 * dengan kelas SIM Kepolisian dan Surat Izin Operator bagi alat angkat;
 * ditulis sebagai cabang di dalam kode, aturan itu harus disunting
 * programmer setiap kali daftar unitnya bertambah.
 */
class Kendaraan extends Master
{
    protected $table = 'mnr_kendaraan';

    protected function casts(): array
    {
        return parent::casts() + ['wajib_sio' => 'boolean'];
    }

    public function sub(): HasMany
    {
        return $this->hasMany(SubKendaraan::class, 'kendaraan_id')->orderBy('nama');
    }
}
