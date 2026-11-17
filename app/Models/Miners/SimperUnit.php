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

    /**
     * Kewenangan per unit yang dicetak di belakang kartu — `authority`
     * Safe Track. T dipertahankan untuk data yang sudah memakainya.
     */
    public const AUTHORITY = [
        'F' => 'F — Full',
        'R' => 'R — Restricted',
        'P' => 'P — Probation',
        'I' => 'I — Instructor',
        'T' => 'T',
    ];

    /** Arti huruf kewenangan pada kartu — ARTI_KELAS Safe Track. */
    public const ARTI_AUTHORITY = [
        'F' => 'PENUH',
        'R' => 'KEADAAN DARURAT',
        'P' => 'PERCOBAAN',
        'I' => 'PELATIHAN',
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

    /** Seluruh type unit pada baris ini — satu baris dapat memuat beberapa. */
    public function jenisUnitBanyak(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(JenisUnit::class, 'mnr_simper_unit_jenis', 'simper_unit_id', 'jenis_unit_id')
            ->withTimestamps();
    }

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
