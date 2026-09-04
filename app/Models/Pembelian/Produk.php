<?php

namespace App\Models\Pembelian;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu butir yang dapat dibeli: platformnya, atau satu aplikasi di dalamnya.
 *
 * TIDAK BERLINGKUP PERUSAHAAN. Katalog adalah daftar harga penjual,
 * bukan data pelanggan; menyaringnya per perusahaan akan membuat calon
 * pembeli yang belum punya perusahaan melihat katalog kosong.
 */
class Produk extends Model
{
    protected $table = 'beli_produk';

    public const WEBSITE  = 'website';
    public const APLIKASI = 'aplikasi';

    protected $fillable = [
        'kode', 'nama', 'jenis', 'modul_kunci', 'keterangan',
        'harga', 'masa_bulan', 'aktif', 'urutan',
    ];

    protected function casts(): array
    {
        return ['harga' => 'integer', 'masa_bulan' => 'integer', 'aktif' => 'boolean'];
    }

    public function scopeAktif($q) { return $q->where('aktif', true); }

    /** Masa berlaku sebagai kalimat, bukan angka telanjang. */
    public function masaBerlaku(): string
    {
        if ($this->masa_bulan === 0)  return 'Berlaku selamanya';
        if ($this->masa_bulan === 12) return 'Berlaku 1 tahun';

        return 'Berlaku '.$this->masa_bulan.' bulan';
    }
}
