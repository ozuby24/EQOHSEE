<?php

use App\Models\Pembelian\Produk;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Harga bertingkat untuk website kedua dan seterusnya.
 *
 * ── SATU KOLOM, BUKAN TABEL TANGGA HARGA ──
 *
 * Yang diminta sederhana dan tidak akan berubah bentuknya: butir
 * pertama berharga penuh, setiap butir berikutnya berharga tetap.
 * Tabel tangga harga bertingkat-tingkat (1-4 sekian, 5-9 sekian)
 * menjawab pertanyaan yang belum pernah ditanyakan, dan setiap tingkat
 * yang tidak dipakai adalah satu cabang lagi yang harus diuji, dibaca,
 * dan dijelaskan kepada yang mengubah harganya nanti.
 *
 * NULL berarti tidak bertingkat — harga × banyaknya, persis seperti
 * sebelum kolom ini ada. Dibedakan dengan sengaja dari nol: nol adalah
 * harga yang sah untuk butir tambahan (gratis), sedangkan null berarti
 * "tidak ada aturan tambahan di sini". Keduanya memberi total yang
 * sangat berbeda dan tidak boleh tertukar.
 *
 * ── IKUT DISALIN KE BARIS TAGIHAN ──
 *
 * Sama seperti `harga` dan `nama`, dengan alasan yang sama: menurunkan
 * harga website tambahan bulan depan tidak boleh mengubah nilai tagihan
 * yang sudah terkirim. Tanpa salinan ini, subtotal yang tersimpan tidak
 * dapat lagi dijelaskan dari angka mana pun yang masih ada di basis
 * data — dan tagihan yang totalnya tidak dapat diterangkan adalah
 * tagihan yang tidak dapat dipertanggungjawabkan kepada pembelinya.
 */
return new class extends Migration
{
    /** Website kedua dan seterusnya, dalam satu pesanan yang sama. */
    private const WEBSITE_TAMBAHAN = 2_000_000;

    public function up(): void
    {
        Schema::table('beli_produk', function (Blueprint $t) {
            $t->bigInteger('harga_tambahan')->nullable()->after('harga');
        });

        Schema::table('beli_item', function (Blueprint $t) {
            $t->bigInteger('harga_tambahan')->nullable()->after('harga');
        });

        /* Paket website yang sudah terpasang diberi harga tambahannya.
           Hanya bila barisnya ada: pemasangan baru belum menjalankan
           pembelian:katalog, dan migrasi yang memaksa barisnya ada akan
           membuat katalog terbit tanpa lewat perintah yang memang
           bertugas menerbitkannya. */
        Produk::where('kode', 'WEBSITE')->update([
            'harga_tambahan' => self::WEBSITE_TAMBAHAN,
        ]);
    }

    public function down(): void
    {
        Schema::table('beli_produk', fn (Blueprint $t) => $t->dropColumn('harga_tambahan'));
        Schema::table('beli_item',   fn (Blueprint $t) => $t->dropColumn('harga_tambahan'));
    }
};
