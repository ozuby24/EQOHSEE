<?php

use App\Models\Pembelian\Produk;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Server Professional yang spesifikasinya dapat dipilih pembeli.
 *
 * Keputusan harga pemiliknya: dasar 1 vCPU, RAM 1 GB, NVMe 60 GB,
 * HTTPS, cadangan, dan pemantauan Rp 3.000.000 per tahun; setiap vCPU
 * tambahan Rp 500.000 dan setiap GB RAM tambahan Rp 500.000. Lima
 * aplikasi dengan 4 vCPU dan RAM 4 GB karena itu Rp 6.000.000.
 *
 * ── HARGA TAMBAHANNYA DISIMPAN PADA PRODUK, BUKAN DI KODE ──
 *
 * NULL berarti butirnya tidak dapat diatur — seluruh katalog lama tetap
 * dihitung persis seperti sebelumnya. Sama seperti harga_tambahan.
 *
 * ── SPESIFIKASI YANG DIPESAN IKUT DISALIN KE BARIS TAGIHAN ──
 *
 * Bersama harga per vCPU dan per GB-nya. Mengubah tarif tambahan bulan
 * depan tidak boleh mengubah nilai tagihan yang sudah terkirim, dan
 * tagihan yang menyebut "Rp 6.000.000" tanpa menyebut 4 vCPU dan 4 GB
 * tidak dapat diterangkan kepada pembelinya.
 */
return new class extends Migration
{
    private const PER_CPU = 500_000;
    private const PER_GB  = 500_000;

    public function up(): void
    {
        Schema::table('beli_produk', function (Blueprint $t) {
            $t->bigInteger('harga_cpu')->nullable()->after('harga_tambahan');
            $t->bigInteger('harga_ram')->nullable()->after('harga_cpu');
        });

        Schema::table('beli_item', function (Blueprint $t) {
            $t->bigInteger('harga_cpu')->nullable()->after('harga_tambahan');
            $t->bigInteger('harga_ram')->nullable()->after('harga_cpu');
            $t->unsignedSmallInteger('cpu')->nullable()->after('harga_ram');
            $t->unsignedSmallInteger('ram')->nullable()->after('cpu');
        });

        /* Hanya butir layanan server yang sudah terpasang. Pemasangan baru
           mendapatkannya dari pembelian:katalog. */
        Produk::where('kode', 'LAYANAN-PRO')->update([
            'harga_cpu' => self::PER_CPU,
            'harga_ram' => self::PER_GB,
        ]);
    }

    public function down(): void
    {
        Schema::table('beli_produk', fn (Blueprint $t) => $t->dropColumn(['harga_cpu', 'harga_ram']));
        Schema::table('beli_item', fn (Blueprint $t) => $t->dropColumn(['harga_cpu', 'harga_ram', 'cpu', 'ram']));
    }
};
