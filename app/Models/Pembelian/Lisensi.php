<?php

namespace App\Models\Pembelian;

use App\Models\Company;
use App\Support\Authority;
use Illuminate\Database\Eloquent\Model;

/** Hak pakai satu produk oleh satu perusahaan, dengan masa berlakunya. */
class Lisensi extends Model
{
    protected $table = 'beli_lisensi';

    protected $fillable = [
        'pesanan_id', 'produk_id', 'company_id', 'kunci', 'modul_kunci',
        'mulai', 'berakhir', 'aktif',
    ];

    protected function casts(): array
    {
        return ['mulai' => 'date', 'berakhir' => 'date', 'aktif' => 'boolean'];
    }

    public function pesanan() { return $this->belongsTo(Pesanan::class, 'pesanan_id'); }
    public function produk()  { return $this->belongsTo(Produk::class, 'produk_id'); }
    public function company() { return $this->belongsTo(Company::class); }

    /**
     * Masih berlaku hari ini?
     *
     * `berakhir` kosong berarti TANPA BATAS, bukan sudah lewat. Keduanya
     * sama-sama null bila dibandingkan sembarangan, dan menyamakannya
     * membuat lisensi beli-putus terbaca sebagai lisensi mati.
     */
    public function berlaku(): bool
    {
        if (! $this->aktif) return false;
        if ($this->berakhir === null) return true;

        return Authority::sisaHari($this->berakhir) >= 0;
    }
}
