<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use App\Support\Authority;
use Illuminate\Database\Eloquent\Model;

/**
 * Pemeriksaan kesehatan berkala.
 *
 * Yang disimpan hanya KESIMPULAN kelayakan kerjanya, bukan rincian
 * medisnya. Rincian medis adalah rekam medis: ia punya aturan
 * kerahasiaannya sendiri dan tidak boleh terbaca oleh setiap admin HSE
 * yang membuka daftar pekerja.
 */
class PasporMcu extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'paspor';

    protected $table = 'paspor_mcu';

    protected $fillable = [
        'paspor_id', 'tgl_periksa', 'tgl_expired', 'penyelenggara',
        'jenis', 'hasil', 'pembatasan', 'berkas',
    ];

    protected function casts(): array
    {
        return ['tgl_periksa' => 'date', 'tgl_expired' => 'date'];
    }

    public function paspor() { return $this->belongsTo(Paspor::class); }

    public function keadaan(): string    { return Authority::keadaan($this->tgl_expired); }
    public function keterangan(): string { return Authority::keterangan($this->tgl_expired); }

    /** Hasilnya membolehkan bekerja — terpisah dari masa berlakunya. */
    public function hasilLayak(): bool
    {
        return in_array($this->hasil, Authority::MCU_LAYAK, true);
    }
}
