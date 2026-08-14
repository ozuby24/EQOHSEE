<?php

namespace App\Models;

use App\Models\Concerns\BerpemilikPerusahaan;
use App\Models\Scopes\MilikPerusahaan;
use App\Support\Geometri;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ScopedBy(MilikPerusahaan::class)]
class MineMapLayer extends Model
{
    use BerpemilikPerusahaan;

    /**
     * Kolom ukuran sengaja tidak dapat diisi dari luar.
     *
     * Luas, keliling, dan titik tengah adalah turunan dari geojson;
     * membiarkannya diisi lewat payload berarti membolehkan sebuah layer
     * menyebut luasnya sendiri berbeda dari koordinatnya, dan tidak ada
     * yang dapat mengetahuinya dari angkanya saja. Nilainya dihitung
     * ulang pada setiap penyimpanan, di bawah.
     */
    protected $fillable = [
        'company_id', 'user_id', 'nama', 'tipe', 'geojson', 'warna', 'status',
        'catatan', 'tanggal_survey', 'sumber_survey',
    ];

    protected function casts(): array
    {
        return [
            'luas_m2'        => 'float',
            'keliling_m'     => 'float',
            'titik_lon'      => 'float',
            'titik_lat'      => 'float',
            'jumlah_fitur'   => 'integer',
            'tanggal_survey' => 'date',
        ];
    }

    /**
     * Ukuran dihitung ulang setiap kali geojson-nya berubah.
     *
     * Diletakkan di model, bukan di controller, supaya perintah artisan,
     * penyemai, dan modul yang ditulis kemudian ikut terjaga. Ukuran yang
     * hanya dihitung di satu jalur akan tertinggal pada jalur lainnya,
     * dan yang tertinggal tidak menimbulkan galat — hanya hektare lama
     * yang menempel pada bentuk yang sudah berubah.
     */
    protected static function booted(): void
    {
        static::saving(function (self $layer) {
            if (!$layer->isDirty('geojson') && $layer->exists) return;

            $u = Geometri::ukur((string) $layer->geojson);

            $layer->luas_m2      = $u['luas_m2'];
            $layer->keliling_m   = $u['keliling_m'];
            $layer->titik_lon    = $u['titik'][0] ?? null;
            $layer->titik_lat    = $u['titik'][1] ?? null;
            $layer->jumlah_fitur = $u['fitur'];
        });
    }

    public function company(): BelongsTo { return $this->belongsTo(Company::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    public function hektare(): float
    {
        return Geometri::hektare($this->luas_m2);
    }

    /** Kilometer, untuk tipe yang berupa garis seperti jalan angkut. */
    public function panjangKm(): float
    {
        return round($this->keliling_m / 1000, 3);
    }

    /**
     * Ringkasan tanpa geojson.
     *
     * Dipakai pada daftar dan hitungan; muatan halaman tidak perlu
     * membawa koordinatnya, dan pernah membengkak sampai puluhan
     * megabita ketika ia ikut terbawa.
     */
    public function toView(bool $sertakanGeojson = false): array
    {
        $dasar = [
            'id'             => $this->id,
            'nama'           => $this->nama,
            'tipe'           => $this->tipe,
            'warna'          => $this->warna,
            'status'         => $this->status,
            'catatan'        => $this->catatan,
            'luas_m2'        => $this->luas_m2,
            'hektare'        => $this->hektare(),
            'keliling_m'     => $this->keliling_m,
            'panjang_km'     => $this->panjangKm(),
            'titik'          => $this->titik_lon !== null ? [$this->titik_lon, $this->titik_lat] : null,
            'jumlah_fitur'   => $this->jumlah_fitur,
            'tanggal_survey' => $this->tanggal_survey?->toDateString(),
            'sumber_survey'  => $this->sumber_survey,
            'perusahaan'     => $this->company?->name,
        ];

        return $sertakanGeojson ? $dasar + ['geojson' => $this->geojson] : $dasar;
    }
}
