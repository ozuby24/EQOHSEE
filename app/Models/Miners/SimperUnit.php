<?php

namespace App\Models\Miners;

use App\Models\Concerns\BerindukPerusahaan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu unit yang boleh dikemudikan, beserta nilai ujinya.
 *
 * `asal` menyimpan DARI MANA baris ini datang — pengajuan baru,
 * penambahan unit, upgrade, atau perpanjangan. Tanpa itu, kartu yang
 * unitnya bertambah tiga kali tidak dapat menjelaskan kapan dan lewat
 * pengajuan mana tiap unitnya masuk, dan pertanyaan itu justru yang
 * ditanyakan ketika sebuah unit dipersoalkan.
 */
class SimperUnit extends Model
{
    use BerindukPerusahaan;

    protected static string $indukPerusahaan = 'simper';

    public const KEWENANGAN = [
        'operator' => 'Operator',
        'pengawas' => 'Pengawas',
        'trainer'  => 'Trainer',
    ];

    public const ASAL = [
        'baru'         => 'Pengajuan baru',
        'penambahan'   => 'Penambahan unit',
        'upgrade'      => 'Upgrade kelas',
        'perpanjangan' => 'Perpanjangan',
    ];

    /** Nilai kelulusan uji praktik dan teori. */
    public const NILAI_LULUS = 70;

    protected $table = 'mnr_simper_unit';

    protected $guarded = ['id'];

    protected $attributes = ['asal' => 'baru'];

    protected function casts(): array
    {
        return [
            'nilai_p2h'     => 'integer',
            'nilai_praktek' => 'integer',
            'nilai_teori'   => 'integer',
            'nilai_rambu'   => 'integer',
        ];
    }

    public function simper(): BelongsTo     { return $this->belongsTo(Simper::class, 'simper_id'); }
    public function kendaraan(): BelongsTo  { return $this->belongsTo(Kendaraan::class, 'kendaraan_id'); }
    public function jenisUnit(): BelongsTo  { return $this->belongsTo(JenisUnit::class, 'jenis_unit_id'); }

    /**
     * Seluruh nilai yang terisi sudah mencapai ambang kelulusan.
     *
     * Yang KOSONG tidak dianggap gagal, dan itu pembedaan yang penting:
     * uji rambu tidak berlaku bagi seluruh golongan unit, dan
     * memperlakukan kolom kosong sebagai nol akan menggagalkan operator
     * yang sesungguhnya tidak pernah diuji butir itu.
     */
    public function lulus(): bool
    {
        foreach (['nilai_p2h', 'nilai_praktek', 'nilai_teori', 'nilai_rambu'] as $k) {
            if ($this->$k !== null && $this->$k < self::NILAI_LULUS) return false;
        }

        return true;
    }
}
