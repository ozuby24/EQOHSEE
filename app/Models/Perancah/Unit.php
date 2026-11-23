<?php

namespace App\Models\Perancah;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Perancah\Katalog;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{HasMany, HasOne};

/**
 * Satu perancah terpasang: spesifikasi, lokasi, dan keadaan tag-nya.
 *
 * `status_tag` adalah salinan warna tag aktif, disimpan supaya peta dan
 * daftar dapat disaring tanpa menggabungkan tabel tag. Kebenarannya tetap
 * pada tag: tag hijau/kuning yang lewat masa berlakunya dibaca sebagai
 * KEDALUWARSA oleh `statusEfektif()` walau penjadwal belum sempat
 * menerbitkan tag merahnya.
 */
#[ScopedBy(MilikPerusahaan::class)]
class Unit extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'perancah_unit';

    protected $fillable = [
        'company_id', 'user_id', 'kode', 'judul', 'jenis', 'kelas_beban',
        'panjang_m', 'lebar_m', 'tinggi_m', 'jumlah_dek',
        'zona', 'peralatan_ref', 'nomor_izin', 'lat', 'lng', 'elevasi_m',
        'jenis_tanah', 'jumlah_ikatan', 'angin_kmh', 'ada_guardrail', 'ada_toeboard', 'ada_outrigger',
        'kontraktor', 'scaffolder_nama', 'scaffolder_sertifikat', 'tanggal_pasang', 'rencana_bongkar',
        'status_tag', 'kode_tag', 'inspeksi_berikut', 'dibongkar_at', 'catatan',
    ];

    protected function casts(): array
    {
        return [
            'panjang_m' => 'float', 'lebar_m' => 'float', 'tinggi_m' => 'float', 'jumlah_dek' => 'integer',
            'lat' => 'float', 'lng' => 'float', 'elevasi_m' => 'float', 'angin_kmh' => 'float',
            'jumlah_ikatan' => 'integer',
            'ada_guardrail' => 'boolean', 'ada_toeboard' => 'boolean', 'ada_outrigger' => 'boolean',
            'tanggal_pasang' => 'date', 'rencana_bongkar' => 'date',
            'inspeksi_berikut' => 'datetime', 'dibongkar_at' => 'datetime',
        ];
    }

    public function hitung(): HasOne { return $this->hasOne(Hitung::class, 'unit_id'); }
    public function kajian(): HasMany { return $this->hasMany(Kajian::class, 'unit_id'); }
    public function kajianTerakhir(): HasOne { return $this->hasOne(Kajian::class, 'unit_id')->latestOfMany(); }
    public function inspeksi(): HasMany { return $this->hasMany(Inspeksi::class, 'unit_id'); }
    public function inspeksiTerakhir(): HasOne { return $this->hasOne(Inspeksi::class, 'unit_id')->latestOfMany('diperiksa_at'); }
    public function tag(): HasMany { return $this->hasMany(Tag::class, 'unit_id'); }
    public function tagAktif(): HasOne { return $this->hasOne(Tag::class, 'unit_id')->where('aktif', true)->latestOfMany(); }

    /** Spesifikasi dalam bentuk yang dibaca kalkulator dan kajian. */
    public function spek(): array
    {
        return $this->only([
            'jenis', 'kelas_beban', 'panjang_m', 'lebar_m', 'tinggi_m', 'jumlah_dek',
            'jenis_tanah', 'jumlah_ikatan', 'angin_kmh', 'ada_guardrail', 'ada_toeboard', 'ada_outrigger',
        ]);
    }

    /** hijau, kuning, merah, kedaluwarsa, atau dibongkar. */
    public function statusEfektif(): string
    {
        if ($this->status_tag === 'dibongkar') return 'dibongkar';
        if (in_array($this->status_tag, ['hijau', 'kuning'], true)
            && $this->inspeksi_berikut && $this->inspeksi_berikut->isPast()) {
            return 'kedaluwarsa';
        }

        return $this->status_tag;
    }

    public function swl(): int
    {
        return Katalog::swl((string) $this->kelas_beban);
    }

    public function dimensi(): string
    {
        $f = fn ($x) => rtrim(rtrim(number_format((float) $x, 2, ',', ''), '0'), ',');
        return $f($this->panjang_m).' × '.$f($this->lebar_m).' × '.$f($this->tinggi_m).' m';
    }
}
