<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anggaran tahunan satu akun pada satu pusat biaya.
 *
 * Tidak memakai alur tinjauan: anggaran adalah keputusan yang sudah
 * diambil di luar aplikasi — pada RKAB yang disahkan — dan yang
 * dikerjakan di sini hanya menyalinnya agar realisasi punya pembanding.
 * Yang perlu ditinjau adalah realisasinya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class BiayaAnggaran extends Model
{
    use BerpemilikPerusahaan;

    public const PUSAT = [
        'penambangan', 'pengangkutan', 'pengolahan', 'perawatan', 'penunjang', 'umum',
    ];

    protected $fillable = [
        'company_id', 'biaya_akun_id', 'tahun', 'pusat_biaya',
        'nilai_rp', 'kuantitas_rencana', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'tahun'             => 'integer',
            'nilai_rp'          => 'float',
            'kuantitas_rencana' => 'float',
        ];
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function akun(): BelongsTo    { return $this->belongsTo(BiayaAkun::class, 'biaya_akun_id'); }

    /** Harga rencana per satuan — diturunkan, tidak disimpan. */
    public function hargaRencana(): ?float
    {
        return \App\Support\Biaya::perSatuan($this->nilai_rp, $this->kuantitas_rencana);
    }

    public function toView(): array
    {
        return [
            'id'        => $this->id,
            'akunId'    => $this->biaya_akun_id,
            'akun'      => $this->akun?->kode,
            'akunNama'  => $this->akun?->nama,
            'satuan'    => $this->akun?->satuan,
            'tahun'     => $this->tahun,
            'pusat'     => $this->pusat_biaya,
            'nilai'     => $this->nilai_rp,
            'kuantitas' => $this->kuantitas_rencana,
            'harga'     => $this->hargaRencana(),
            'catatan'   => $this->catatan,
        ];
    }
}
