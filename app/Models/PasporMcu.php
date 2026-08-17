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
        'paspor_id', 'mcu_pengajuan_id', 'tgl_periksa', 'tgl_expired',
        'penyelenggara', 'nomor', 'jenis', 'hasil', 'pembatasan',
        'rujukan', 'outstanding', 'berkas',
    ];

    protected function casts(): array
    {
        return [
            'tgl_periksa' => 'date',
            'tgl_expired' => 'date',
            'outstanding' => 'date',
        ];
    }

    public function paspor() { return $this->belongsTo(Paspor::class); }

    public function pengajuan()
    {
        return $this->belongsTo(McuPengajuan::class, 'mcu_pengajuan_id');
    }

    public function keadaan(): string    { return Authority::keadaan($this->tgl_expired); }
    public function keterangan(): string { return Authority::keterangan($this->tgl_expired); }

    /** Hasilnya membolehkan bekerja — terpisah dari masa berlakunya. */
    public function hasilLayak(): bool
    {
        return in_array($this->hasil, Authority::MCU_LAYAK, true);
    }

    /**
     * Rujukan medis yang tanggal tindak lanjutnya sudah lewat.
     *
     * Hasil "Fit With Note" yang dirujuk tetapi tidak pernah ditagih
     * adalah catatan yang sudah lengkap di berkas dan tidak pernah
     * terjadi di kenyataan. Yang membuatnya tertagih adalah tanggal —
     * karena itu rujukan tanpa tanggal ikut terhitung tertunggak di
     * sini, bukan diabaikan.
     */
    public function rujukanTertunggak(): bool
    {
        if (blank($this->rujukan)) return false;

        return blank($this->outstanding)
            || Authority::sisaHari($this->outstanding) < 0;
    }
}
