<?php

namespace App\Models\Miners;

use App\Models\Concerns\BerindukPerusahaan;
use App\Models\Miners\Concerns\PunyaAlur;
use App\Models\User;
use App\Support\Miners\Acuan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

/**
 * Pengajuan lanjutan atas sebuah SIMPER yang sudah terbit.
 *
 * TIGA JENIS PADA SATU TABEL — penambahan unit, upgrade kelas,
 * perpanjangan — dibedakan kolom `jenis`. Project1 menulisnya sebagai
 * tiga rangkaian tabel terpisah yang isinya hampir sama, lengkap dengan
 * tiga tabel alur tersendiri; akibatnya tiap perubahan alur dikerjakan
 * tiga kali, dan yang tertinggal adalah yang paling jarang dibuka.
 */
class SimperAjuan extends Model
{
    use BerindukPerusahaan;
    use PunyaAlur;

    protected static string $indukPerusahaan = 'simper';

    protected static string $jenisDokumen = 'ajuan';

    public const JENIS = [
        'penambahan'   => 'Penambahan Unit',
        'upgrade'      => 'Upgrade Kelas',
        'perpanjangan' => 'Perpanjangan',
    ];

    public const STATUS = [
        'draf'     => 'Draf',
        'diajukan' => 'Diajukan',
        'ohse'     => 'Verifikasi OHSE',
        'ktt'      => 'Menunggu Pengesahan KTT',
        'selesai'  => 'Selesai',
        'ditolak'  => 'Ditolak',
    ];

    /**
     * Perpanjangan paling awal boleh diajukan sebulan sebelum berakhir.
     *
     * SOP menyebutnya tegas. Di Project1 ambang ini hanya dipakai
     * memberi warna pada layar pemantauan dan tidak pernah membatasi
     * kapan pengajuan boleh dibuat — sehingga perpanjangan dapat masuk
     * sepanjang tahun, dan antrean OHSE terisi berkas yang belum
     * waktunya.
     */
    public const HARI_BOLEH_PERPANJANG = Acuan::MASA['buka_perpanjangan_hari'];

    protected $table = 'mnr_simper_ajuan';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'draf'];

    protected function casts(): array
    {
        return ['tanggal' => 'date', 'simpol_berlaku_sampai' => 'date'];
    }

    public function simper(): BelongsTo { return $this->belongsTo(Simper::class, 'simper_id'); }
    public function user(): BelongsTo   { return $this->belongsTo(User::class); }

    public function unit(): HasMany
    {
        return $this->hasMany(SimperAjuanUnit::class, 'ajuan_id')->orderBy('id');
    }

    public function jenisLabel(): string
    {
        return self::JENIS[$this->jenis] ?? $this->jenis;
    }
}
