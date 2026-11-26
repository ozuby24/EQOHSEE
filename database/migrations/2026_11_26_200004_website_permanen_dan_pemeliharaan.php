<?php

use App\Models\Pembelian\Produk;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website sekali bayar; yang tahunan hanya server dan pemeliharaan.
 *
 * Keputusan pemiliknya, supaya katalog tidak menyesatkan: harga website
 * BUKAN harga setahun — lisensinya permanen. Yang dibayar setiap tahun
 * hanya dua: server (mulai Rp 3.000.000) dan pemeliharaan Rp 2.000.000
 * untuk setiap kelipatan tiga modul, di luar server. Satu sampai tiga
 * modul karena itu Rp 5.000.000 setahun.
 *
 * `modul_per_blok` adalah ukuran satu kelipatan. NULL berarti butirnya
 * tidak dihitung per modul — seluruh katalog lama tetap seperti semula.
 *
 * Butir pemeliharaannya sendiri DITERBITKAN pembelian:katalog, yang
 * berjalan sesudah migrasi pada setiap deploy — bukan di sini, sama
 * seperti butir katalog lainnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('beli_produk', 'modul_per_blok')) {
            Schema::table('beli_produk', function (Blueprint $t) {
                $t->unsignedTinyInteger('modul_per_blok')->nullable()->after('harga_ram');
            });
        }

        /* Website: permanen. Tagihan yang sudah terbit tidak disentuh —
           masa berlakunya tersalin di baris tagihannya sendiri. */
        Produk::where('kode', 'WEBSITE')->update(['masa_bulan' => 0]);
    }

    public function down(): void
    {
        Produk::where('kode', 'WEBSITE')->where('masa_bulan', 0)->update(['masa_bulan' => 12]);

        if (Schema::hasColumn('beli_produk', 'modul_per_blok')) {
            Schema::table('beli_produk', fn (Blueprint $t) => $t->dropColumn('modul_per_blok'));
        }
    }
};
