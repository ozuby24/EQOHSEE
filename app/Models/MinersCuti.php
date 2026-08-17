<?php

namespace App\Models;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\Concerns\Ditinjau;
use App\Support\Alur;
use App\Support\Tahap;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Satu pengajuan cuti.
 *
 * Jumlah harinya DISIMPAN, bukan dihitung ulang dari selisih tanggal
 * setiap kali dibaca. Yang menentukan berapa hari terpotong dari jatah
 * adalah keputusan saat cuti itu disetujui — termasuk bila ada hari
 * libur di tengahnya yang tidak ikut dihitung. Menghitungnya ulang
 * akan diam-diam mengubah sisa cuti orang yang cutinya sudah lama
 * disetujui, setiap kali aturan hari libur diubah, tanpa satu pun jejak
 * yang menunjukkan angkanya pernah berbeda.
 *
 * HANYA CUTI TAHUNAN YANG MEMOTONG JATAH. Sakit, melahirkan, dan cuti
 * penting punya dasar hukumnya sendiri dan tidak diambil dari dua belas
 * hari itu; memotongnya berarti menghukum orang karena jatuh sakit.
 */
class MinersCuti extends Model
{
    use BerindukPerusahaan;
    use Ditinjau;

    protected static string $indukPerusahaan = 'paspor';

    protected $table = 'miners_cuti';

    protected $fillable = [
        'paspor_id', 'tahun', 'jenis', 'mulai', 'selesai', 'jumlah_hari',
        'alamat_cuti', 'kontak', 'pengganti_id', 'alasan',
    ];

    protected function casts(): array
    {
        return ['mulai' => 'date', 'selesai' => 'date',
                'tahun' => 'integer', 'jumlah_hari' => 'integer'];
    }

    public const JENIS = ['Tahunan', 'Sakit', 'Melahirkan', 'Penting', 'Tanpa Gaji'];

    /** Jenis yang mengurangi jatah cuti tahunan. Hanya satu. */
    public const MEMOTONG_JATAH = ['Tahunan'];

    public function paspor()    { return $this->belongsTo(Paspor::class); }
    public function pengganti() { return $this->belongsTo(Paspor::class, 'pengganti_id'); }

    public function dapatDitinjauOleh(?User $u): bool
    {
        if (!Tahap::penentu($u))        return false;
        if (!$this->menungguTinjauan()) return false;

        return $this->diajukan_oleh !== $u?->getKey();
    }

    public function memotongJatah(): bool
    {
        return in_array($this->jenis, self::MEMOTONG_JATAH, true);
    }

    public function sedangCuti(?Carbon $kini = null): bool
    {
        if (!$this->sudahDisetujui()) return false;

        $kini = ($kini ?: Carbon::now())->copy()->startOfDay();

        return $kini->betweenIncluded(
            $this->mulai->copy()->startOfDay(),
            $this->selesai->copy()->startOfDay(),
        );
    }

    /**
     * Hari kalender antara dua tanggal, inklusif kedua ujungnya.
     *
     * Dipakai sebagai USULAN saat formulir diisi, bukan sebagai nilai
     * yang dipaksakan — pengaju tetap dapat menurunkannya bila ada hari
     * libur di tengahnya. Inklusif karena cuti sehari, dari tanggal 5
     * sampai tanggal 5, adalah satu hari dan bukan nol.
     */
    public static function hariKalender($mulai, $selesai): int
    {
        return (int) Carbon::parse($mulai)->startOfDay()
            ->diffInDays(Carbon::parse($selesai)->startOfDay()) + 1;
    }

    /**
     * Cuti yang terhitung memakai jatah.
     *
     * Yang DIAJUKAN ikut terhitung, bukan hanya yang disetujui — dan itu
     * disengaja. Sisa jatah dipakai memutuskan apakah pengajuan
     * berikutnya boleh dibuat; bila yang masih menunggu tidak dihitung,
     * seseorang dapat mengajukan lima cuti sekaligus yang masing-masing
     * tampak muat, lalu seluruhnya disetujui dan jatahnya minus.
     */
    public function scopeMembebaniJatah(Builder $q): Builder
    {
        return $q->whereIn('jenis', self::MEMOTONG_JATAH)
                 ->whereIn('status', [Alur::DIAJUKAN, Alur::DISETUJUI]);
    }
}
