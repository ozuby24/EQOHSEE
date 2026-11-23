<?php

namespace App\Models\Angkat;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Models\User;
use App\Support\Angkat\Katalog;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

/**
 * Satu rencana pengangkatan (lifting plan): masukan, hasil simulasi
 * 4 fase, personel, alat bantu, dan status persetujuannya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Rencana extends Model
{
    use BerpemilikPerusahaan;

    /** Status yang masih boleh diubah isinya. */
    public const TERBUKA = ['draf', 'ditolak'];

    protected $table = 'angkat_rencana';

    protected $fillable = [
        'company_id', 'user_id', 'alat_id', 'kode', 'judul', 'kategori_beban', 'klasifikasi', 'jenis_alat',
        'lokasi', 'area', 'jadwal_at', 'masukan', 'hasil', 'gear',
        'berat_bersih_t', 'berat_kotor_t', 'radius_m', 'boom_m', 'kapasitas_t', 'utilisasi_pct',
        'sudut_sling', 'tegangan_maks_t', 'tekanan_tanah', 'putusan', 'skor',
        'operator_nama', 'operator_sio', 'rigger_nama', 'rigger_sertifikat', 'signalman_nama', 'pengawas_nama',
        'status', 'disetujui_oleh', 'disetujui_at', 'catatan_persetujuan', 'selesai_at', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'masukan' => 'array', 'hasil' => 'array', 'gear' => 'array',
            'berat_bersih_t' => 'float', 'berat_kotor_t' => 'float', 'radius_m' => 'float', 'boom_m' => 'float',
            'kapasitas_t' => 'float', 'utilisasi_pct' => 'float', 'sudut_sling' => 'float',
            'tegangan_maks_t' => 'float', 'tekanan_tanah' => 'float', 'skor' => 'integer',
            'jadwal_at' => 'datetime', 'disetujui_at' => 'datetime', 'selesai_at' => 'datetime',
        ];
    }

    public function alat(): BelongsTo { return $this->belongsTo(Alat::class, 'alat_id'); }
    public function pembuat(): BelongsTo { return $this->belongsTo(User::class, 'user_id'); }
    public function kajian(): HasOne { return $this->hasOne(Kajian::class, 'rencana_id'); }
    public function jejak(): HasMany { return $this->hasMany(Jejak::class, 'rencana_id'); }

    public function terbuka(): bool
    {
        return in_array($this->status, self::TERBUKA, true);
    }

    public function labelBeban(): string
    {
        return Katalog::BEBAN[$this->kategori_beban]['label'] ?? $this->kategori_beban;
    }
}
