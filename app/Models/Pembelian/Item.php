<?php

namespace App\Models\Pembelian;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu baris tagihan.
 *
 * `nama`, `harga`, dan `harga_tambahan` DISALIN dari produk saat dipesan, bukan dibaca
 * ulang lewat relasi. Menaikkan harga daftar bulan depan tidak boleh
 * mengubah nilai tagihan yang sudah terkirim — termasuk yang sudah
 * dibayar, yang akan berubah menjadi kurang bayar tanpa ada yang
 * melakukan apa pun.
 */
class Item extends Model
{
    protected $table = 'beli_item';

    protected $fillable = [
        'pesanan_id', 'produk_id', 'nama', 'harga', 'harga_tambahan',
        'harga_cpu', 'harga_ram', 'cpu', 'ram',
        'masa_bulan', 'jumlah', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'integer', 'harga_tambahan' => 'integer',
            'harga_cpu' => 'integer', 'harga_ram' => 'integer',
            'cpu' => 'integer', 'ram' => 'integer',
            'jumlah' => 'integer',
            'subtotal' => 'integer', 'masa_bulan' => 'integer',
        ];
    }

    public function pesanan() { return $this->belongsTo(Pesanan::class, 'pesanan_id'); }
    public function produk()  { return $this->belongsTo(Produk::class, 'produk_id'); }
}
