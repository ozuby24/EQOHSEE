<?php

namespace App\Models\Investigasi;

use Illuminate\Database\Eloquent\Model;

/**
 * Klasifikasi cedera — istilah regulasi dan istilah statistik sekaligus.
 *
 * Ringan/berat/mati dipakai untuk laporan ke Inspektur Tambang; near
 * miss/FAI/MTI/RWC/LTI dipakai menghitung FR dan SR. Keduanya hidup
 * berdampingan di lapangan, jadi keduanya ada di satu daftar.
 */
class KlasifikasiCedera extends Model
{
    protected $table = 'inv_klasifikasi_cedera';

    protected $fillable = [
        'kode', 'nama', 'hari_min', 'hari_maks', 'hari_hilang_standar', 'keterangan', 'urutan',
    ];

    /**
     * Hari hilang yang dipakai perhitungan SR.
     *
     * Kematian dan cacat tetap memakai angka standar 6.000 hari
     * (Kepdirjen 185/2019), bukan hari absen sebenarnya — yang bagi
     * korban meninggal tidak pernah berhenti bertambah.
     */
    public function hariHilang(?int $aktual): int
    {
        return $this->hari_hilang_standar ?? (int) $aktual;
    }
}
