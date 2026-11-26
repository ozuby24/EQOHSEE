<?php

use App\Models\Pembelian\Produk;
use Illuminate\Database\Migrations\Migration;

/**
 * Website kedua dan seterusnya kini Rp 3.000.000, bukan Rp 2.000.000.
 *
 * Keputusan harga pemiliknya, diberikan langsung — bukan tebakan
 * pemrogram. Lewat migrasi, bukan lewat layar kelola katalog, supaya
 * setiap pemasangan berpindah ke angka yang sama pada deploy yang sama:
 * harga yang diketik tangan di satu server dan lupa di server lain
 * adalah dua etalase yang menawarkan barang yang sama dengan harga
 * berbeda.
 *
 * Baris tagihan yang sudah terbit TIDAK disentuh. `beli_item` menyimpan
 * salinan harganya sendiri persis supaya perubahan seperti ini tidak
 * mengubah nilai tagihan yang sudah terkirim.
 *
 * Hanya baris yang ada yang diubah: pemasangan baru belum menjalankan
 * pembelian:katalog, dan butir WEBSITE-nya lahir di sana dengan harga
 * tambahan yang sama (lihat PasangKatalog::HARGA_WEBSITE_TAMBAHAN).
 */
return new class extends Migration
{
    private const LAMA = 2_000_000;
    private const BARU = 3_000_000;

    public function up(): void
    {
        Produk::where('kode', 'WEBSITE')->update(['harga_tambahan' => self::BARU]);
    }

    public function down(): void
    {
        Produk::where('kode', 'WEBSITE')
            ->where('harga_tambahan', self::BARU)
            ->update(['harga_tambahan' => self::LAMA]);
    }
};
