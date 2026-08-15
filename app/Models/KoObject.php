<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

use App\Support\Ko;
use Illuminate\Database\Eloquent\Model;

#[ScopedBy(MilikPerusahaan::class)]
class KoObject extends Model
{
    use BerpemilikPerusahaan;

    protected $table = 'ko_objects';

    protected $fillable = [
        'kode','nama','kategori','jenis','merk','serial_number','lokasi','company_id',
        'kritikalitas','status_operasi','tgl_sertifikasi','interval_tahun','no_sertifikat',
        'lembaga_uji','lapor_kait','pm_jenis','pm_terakhir','pm_berikutnya','lampiran','keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tgl_sertifikasi' => 'date',
            'pm_terakhir'     => 'date',
            'pm_berikutnya'   => 'date',
            'lapor_kait'      => 'boolean',
            'interval_tahun'  => 'integer',
        ];
    }

    public function company()    { return $this->belongsTo(Company::class); }
    public function safeguards() { return $this->hasMany(KoSafeguard::class)->orderBy('nama'); }
    public function reviews()    { return $this->hasMany(KoReview::class)->latest('tanggal'); }
    public function inspections(){ return $this->hasMany(KoInspection::class)->latest('tanggal'); }
    public function actions()    { return $this->hasMany(KoAction::class)->latest(); }

    /* turunan */
    public function getStatusKoAttribute(): string { return Ko::status($this); }
    public function getSisaHariAttribute(): ?int   { return Ko::sisaHari($this); }
    public function getKadaluarsaAttribute()       { return Ko::kadaluarsa($this); }
    public function getPmTerlewatAttribute(): bool { return Ko::pmTerlewat($this); }
}
