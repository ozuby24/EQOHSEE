<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use App\Support\Authority;
use Illuminate\Database\Eloquent\Model;

/**
 * Kartu masuk area tambang — ID card, SIMPER, atau mine permit.
 *
 * Satu orang dapat memegang lebih dari satu: kartu masuk area dan
 * SIMPER kendaraan adalah dua izin berbeda dengan masa berlaku
 * berbeda, dan menyimpannya sebagai satu baris membuat yang satu
 * menghapus yang lain saat diperpanjang.
 */
class PasporKartu extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'paspor';

    protected $table = 'paspor_kartu';

    protected $fillable = [
        'paspor_id', 'jenis', 'nomor', 'tgl_terbit', 'tgl_expired',
        'golongan', 'area', 'berkas', 'catatan',
    ];

    protected function casts(): array
    {
        return ['tgl_terbit' => 'date', 'tgl_expired' => 'date'];
    }

    public function paspor() { return $this->belongsTo(Paspor::class); }

    public function keadaan(): string    { return Authority::keadaan($this->tgl_expired); }
    public function keterangan(): string { return Authority::keterangan($this->tgl_expired); }
}
