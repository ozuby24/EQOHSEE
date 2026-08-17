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
        'kode','nama','kategori','jenis','ko_unit_master_id','merk','serial_number','lokasi','company_id',
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
    public function unitMaster() { return $this->belongsTo(KoUnitMaster::class, 'ko_unit_master_id'); }

    public function uji()
    {
        return $this->hasMany(KoUjiKelayakan::class, 'ko_object_id')->latest('tgl_inspeksi');
    }

    /**
     * Uji kelayakan terakhir yang SUDAH DISETUJUI.
     *
     * Yang masih draf adalah catatan yang belum ditinjau siapa pun, dan
     * memakainya sebagai dasar status operasi berarti siapa pun dapat
     * memperpanjang izin operasi sebuah alat hanya dengan mengisi
     * formulir.
     */
    public function ujiTerakhir(): ?KoUjiKelayakan
    {
        return $this->relationLoaded('uji')
            ? $this->uji->where('status', \App\Support\Alur::DISETUJUI)
                        ->sortByDesc('tgl_inspeksi')->first()
            : $this->uji()->disetujui()->first();
    }

    /**
     * Tanggal sertifikasi unit berselisih dengan uji terakhirnya?
     *
     * Kolom tgl_sertifikasi tetap menjadi "keadaan sekarang" yang dibaca
     * Ko::status(), dan uji yang disetujui memperbaruinya. Selisih
     * karena itu berarti salah satu dari dua hal, dan keduanya perlu
     * dilihat orang: kolomnya disunting tangan tanpa uji yang
     * mendasarinya, atau ada uji yang disetujui tetapi gagal
     * memperbarui unitnya.
     *
     * Ditampilkan sebagai temuan, bukan diperbaiki diam-diam —
     * pembetulan otomatis akan menutupi sebabnya, dan sebabnya yang
     * penting.
     */
    public function sertifikasiBerselisih(): bool
    {
        $u = $this->ujiTerakhir();

        if (!$u || !$u->tgl_expired) return false;

        return $this->kadaluarsa?->toDateString() !== $u->tgl_expired->toDateString();
    }

    /* turunan */
    public function getStatusKoAttribute(): string { return Ko::status($this); }
    public function getSisaHariAttribute(): ?int   { return Ko::sisaHari($this); }
    public function getKadaluarsaAttribute()       { return Ko::kadaluarsa($this); }
    public function getPmTerlewatAttribute(): bool { return Ko::pmTerlewat($this); }
}
