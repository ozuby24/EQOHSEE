<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pembelian website dan aplikasi di dalamnya.
 *
 * ── UANG DISIMPAN SEBAGAI BILANGAN BULAT RUPIAH ──
 *
 * Bukan desimal, dan sama sekali bukan float. Rupiah tidak dipakai
 * sampai sen dalam transaksi seperti ini, dan `float` menyimpan 0.1
 * sebagai 0.1000000000000000055 — selisih yang tidak terlihat sampai
 * seratus baris dijumlahkan dan totalnya meleset satu rupiah dari yang
 * tertulis di layar. Pada berkas yang dipakai menagih orang, selisih
 * satu rupiah cukup untuk membuat seluruh tagihannya dipertanyakan.
 *
 * bigInteger, bukan integer: integer bertanda berhenti di 2,1 miliar,
 * dan paket tahunan beberapa perusahaan sekaligus melewatinya.
 *
 * ── HARGA DISALIN KE BARIS PESANAN ──
 *
 * `beli_item.harga` menyimpan harga SAAT DIPESAN, bukan merujuk harga
 * produk yang berlaku sekarang. Tanpa itu, menaikkan harga daftar bulan
 * depan diam-diam mengubah nilai seluruh tagihan yang sudah terkirim —
 * termasuk yang sudah dibayar. Nama produknya ikut disalin dengan
 * alasan yang sama.
 *
 * ── TAUTAN BAYAR MEMAKAI TOKEN, BUKAN ID ──
 *
 * Pembeli belum tentu punya akun; ia dikirimi tautan. Kalau tautannya
 * memakai id berurutan, mengganti angkanya menampilkan tagihan orang
 * lain lengkap dengan nama, telepon, dan nilainya. Token acak 40
 * karakter tidak dapat ditebak, dan itulah satu-satunya penjagaan pada
 * halaman yang memang harus terbuka tanpa login.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * Katalog. Dua jenis: 'website' untuk platformnya sebagai satu
         * kesatuan, 'aplikasi' untuk modul di dalamnya.
         *
         * `modul_kunci` menunjuk kunci modul di App\Support\Menu supaya
         * lisensinya kelak dapat dicocokkan dengan menu yang dibuka.
         * Boleh kosong: paket website tidak menunjuk satu modul pun.
         */
        Schema::create('beli_produk', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 40)->unique();
            $t->string('nama', 150);
            $t->string('jenis', 20)->default('aplikasi');
            $t->string('modul_kunci', 40)->nullable();
            $t->text('keterangan')->nullable();

            $t->bigInteger('harga')->default(0);

            /* Berapa bulan lisensinya berlaku. Nol berarti selamanya —
               dibedakan dari null supaya "beli putus" tidak tertukar
               dengan "masa berlakunya belum ditentukan". */
            $t->integer('masa_bulan')->default(12);

            $t->boolean('aktif')->default(true);
            $t->integer('urutan')->default(0);
            $t->timestamps();

            $t->index(['aktif', 'urutan']);
        });

        Schema::create('beli_pesanan', function (Blueprint $t) {
            $t->id();
            $t->string('no_pesanan', 40)->unique()->nullable();

            /* Token tautan bayar. Unik dan wajib — halaman bayar hanya
               dapat dicapai lewatnya. */
            $t->string('token', 64)->unique();

            /* Perusahaan pembeli bila ia sudah menjadi pelanggan.
               Kosong untuk calon pelanggan yang belum punya akun. */
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('pembeli_nama', 150);
            $t->string('pembeli_perusahaan', 150)->nullable();
            $t->string('pembeli_email', 150)->nullable();
            $t->string('pembeli_telepon', 40)->nullable();

            $t->bigInteger('total')->default(0);

            /* draf · menunggu_bayar · menunggu_verifikasi · lunas
               · ditolak · batal · kedaluwarsa */
            $t->string('status', 30)->default('draf');

            $t->timestamp('kedaluwarsa_pada')->nullable();
            $t->text('catatan')->nullable();

            $t->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diverifikasi_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->timestamps();

            /* Diawali company_id: batas perusahaan diterapkan lewat
               kolom itu, dan indeks yang tidak mengawalinya membuat
               tabelnya dipindai penuh tiap kali daftarnya dibuka. */
            $t->index(['company_id', 'status']);
            $t->index(['status', 'created_at']);
        });

        Schema::create('beli_item', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pesanan_id')->constrained('beli_pesanan')->cascadeOnDelete();
            $t->foreignId('produk_id')->nullable()->constrained('beli_produk')->nullOnDelete();

            /* Disalin saat dipesan — lihat catatan di kepala berkas. */
            $t->string('nama', 150);
            $t->bigInteger('harga');
            $t->integer('masa_bulan')->default(12);

            $t->integer('jumlah')->default(1);
            $t->bigInteger('subtotal');
            $t->timestamps();

            $t->unique(['pesanan_id', 'produk_id']);
        });

        /**
         * Bukti bayar.
         *
         * Satu pesanan boleh punya beberapa: kiriman pertama yang salah
         * berkas tidak boleh menghapus jejaknya, sebab yang ditolak pun
         * bagian dari riwayat tagihan.
         */
        Schema::create('beli_pembayaran', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pesanan_id')->constrained('beli_pesanan')->cascadeOnDelete();

            $t->string('metode', 20)->default('qris');   // qris · transfer
            $t->bigInteger('jumlah')->default(0);
            $t->string('atas_nama', 150)->nullable();
            $t->date('tanggal_bayar')->nullable();

            /* Berkas TERTUTUP: tangkapan layar mutasi memuat nomor
               rekening dan saldo pengirimnya. */
            $t->string('bukti', 255)->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();
        });

        /**
         * Lisensi yang terbit sesudah pesanan lunas.
         *
         * Terpisah dari pesanan, bukan kolom di dalamnya: satu pesanan
         * memuat beberapa produk, dan tiap produk punya masa berlakunya
         * sendiri.
         */
        Schema::create('beli_lisensi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('pesanan_id')->constrained('beli_pesanan')->cascadeOnDelete();
            $t->foreignId('produk_id')->nullable()->constrained('beli_produk')->nullOnDelete();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('kunci', 64)->unique();
            $t->string('modul_kunci', 40)->nullable();

            $t->date('mulai');

            /* Null berarti tanpa batas — beli putus. Dibedakan dari
               tanggal yang sudah lewat, dan dari tanggal yang belum
               diisi. */
            $t->date('berakhir')->nullable();

            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beli_lisensi');
        Schema::dropIfExists('beli_pembayaran');
        Schema::dropIfExists('beli_item');
        Schema::dropIfExists('beli_pesanan');
        Schema::dropIfExists('beli_produk');
    }
};
