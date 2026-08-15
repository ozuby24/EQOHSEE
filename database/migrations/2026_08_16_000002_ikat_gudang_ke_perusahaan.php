<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gudang melekat pada perusahaan.
 *
 * `gudang_lokasi` sejak awal punya company_id dan ber-scope rapi.
 * `gudang_barang` dan `gudang_mutasi` tidak — dan keduanya justru yang
 * berisi datanya: register B3 beserta kelas bahaya dan MSDS-nya,
 * seluruh persediaan, dan setiap keluar-masuk barang. Akibatnya setiap
 * pengguna melihat persediaan seluruh perusahaan sebagai satu daftar.
 *
 * Kegagalannya diam dan mudah disalahartikan sebagai fitur: daftarnya
 * terisi, angkanya masuk akal, dan tidak ada yang tampak keliru sampai
 * ada yang mengenali kode barang yang bukan miliknya.
 *
 * Pengisian mundur mengambil pemilik dari lokasinya — satu-satunya
 * penanda kepemilikan yang memang sudah ada pada data lama. Barang
 * tanpa lokasi tetap NULL: NULL berarti "belum dimiliki siapa pun" dan
 * tetap terlihat semua orang, sedangkan menebak pemiliknya berarti
 * memindahkan persediaan seseorang ke perusahaan yang belum tentu
 * benar — dan tebakan itu tidak dapat dibedakan dari data yang sah.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gudang_barang', function (Blueprint $t) {
            $t->foreignId('company_id')->nullable()->after('id')
              ->constrained()->nullOnDelete();
            $t->index(['company_id', 'aktif']);
        });

        Schema::table('gudang_mutasi', function (Blueprint $t) {
            $t->foreignId('company_id')->nullable()->after('id')
              ->constrained()->nullOnDelete();
            $t->index(['company_id', 'tanggal']);
        });

        // Barang mewarisi pemilik dari lokasinya.
        DB::table('gudang_barang')->whereNull('company_id')->whereNotNull('lokasi_id')
            ->update([
                'company_id' => DB::raw(
                    '(SELECT company_id FROM gudang_lokasi WHERE gudang_lokasi.id = gudang_barang.lokasi_id)'
                ),
            ]);

        // Mutasi mewarisi pemilik dari barangnya — bukan dari lokasinya.
        // Satu barang dapat berpindah lokasi; yang tidak berpindah
        // adalah perusahaan pemiliknya.
        DB::table('gudang_mutasi')->whereNull('company_id')->whereNotNull('barang_id')
            ->update([
                'company_id' => DB::raw(
                    '(SELECT company_id FROM gudang_barang WHERE gudang_barang.id = gudang_mutasi.barang_id)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('gudang_mutasi', function (Blueprint $t) {
            $t->dropIndex(['company_id', 'tanggal']);
            $t->dropConstrainedForeignId('company_id');
        });

        Schema::table('gudang_barang', function (Blueprint $t) {
            $t->dropIndex(['company_id', 'aktif']);
            $t->dropConstrainedForeignId('company_id');
        });
    }
};
