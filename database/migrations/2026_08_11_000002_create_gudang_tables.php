<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sistem Informasi Gudang & Penyimpanan.
 *
 * Satu register untuk tiga jenis barang yang perlakuannya berbeda:
 * B3 (bahan berbahaya dan beracun), material/suku cadang, dan APD.
 * Dibedakan lewat kolom `kategori`, bukan lewat tiga tabel terpisah —
 * penerimaan, pengeluaran, dan opname berlaku sama untuk ketiganya, dan
 * tiga salinan alur yang sama akan berbeda perilakunya cepat atau lambat.
 *
 * Yang TIDAK ada di sini: kolom stok.
 *
 * Stok dihitung dari jumlah mutasinya, tidak disimpan. Angka stok yang
 * disimpan adalah ringkasan dari riwayat yang juga tersimpan, dan dua
 * sumber untuk satu kebenaran pasti berselisih — satu pembatalan yang
 * lupa mengurangi, satu galat di tengah transaksi, dan saldonya tidak
 * lagi cocok dengan riwayatnya sendiri tanpa ada yang tahu kapan mulai
 * melenceng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gudang_lokasi', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 30)->unique();
            $t->string('nama', 160);
            $t->string('jenis', 20)->default('umum');   // b3 · material · apd · umum
            $t->string('lokasi', 200)->nullable();
            $t->string('penanggung_jawab', 120)->nullable();

            // Syarat penyimpanan — diisi terutama untuk gudang B3.
            $t->boolean('berventilasi')->default(false);
            $t->boolean('tahan_api')->default(false);
            $t->boolean('ada_tanggul')->default(false);   // secondary containment
            $t->boolean('ada_apar')->default(false);
            $t->boolean('ada_eyewash')->default(false);
            $t->decimal('suhu_maks', 5, 1)->nullable();

            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->text('keterangan')->nullable();
            $t->timestamps();
        });

        Schema::create('gudang_barang', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 40)->unique();
            $t->string('nama', 200);
            $t->string('kategori', 20);                  // b3 · material · apd
            $t->string('satuan', 20)->default('pcs');
            $t->decimal('stok_min', 12, 2)->default(0);
            $t->foreignId('lokasi_id')->nullable()->constrained('gudang_lokasi')->nullOnDelete();

            // B3 — penggolongan dan penanganan.
            $t->string('kelas_b3', 30)->nullable();      // lihat App\Support\Gudang::KELAS_B3
            $t->string('wujud', 20)->nullable();         // padat · cair · gas
            $t->string('un_number', 12)->nullable();
            $t->string('msds')->nullable();              // berkas LDK
            $t->boolean('wajib_msds')->default(false);

            // APD — masa pakai dan ukuran.
            $t->unsignedSmallInteger('masa_pakai_bulan')->nullable();
            $t->string('ukuran', 40)->nullable();

            // Material — penomoran pabrikan.
            $t->string('part_number', 80)->nullable();
            $t->string('merk', 80)->nullable();

            $t->string('foto')->nullable();
            $t->text('keterangan')->nullable();
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['kategori', 'aktif']);
        });

        Schema::create('gudang_mutasi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('barang_id')->constrained('gudang_barang')->cascadeOnDelete();
            $t->string('jenis', 20);                     // masuk · keluar · opname · rusak
            $t->date('tanggal');
            $t->string('nomor', 60)->nullable();         // nomor surat jalan / bon

            /* Selalu positif. Arahnya ditentukan `jenis`, bukan tanda
               bilangan: jumlah bertanda minus membuat setiap penjumlahan
               harus tahu konteksnya, dan satu tempat yang lupa membalik
               tanda menghasilkan stok yang salah tanpa galat. */
            $t->decimal('jumlah', 12, 2);

            // Opname menyimpan hasil hitungan fisiknya; selisih terhadap
            // stok buku dihitung saat dibaca, tidak ikut disimpan.
            $t->decimal('stok_fisik', 12, 2)->nullable();

            $t->string('pihak', 160)->nullable();        // pemasok atau penerima
            $t->foreignId('penerima_id')->nullable()->constrained('users')->nullOnDelete();
            $t->date('kadaluarsa')->nullable();          // per batch, untuk B3 dan APD
            $t->string('batch', 60)->nullable();
            $t->text('keterangan')->nullable();

            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();

            $t->index(['barang_id', 'tanggal']);
            $t->index('jenis');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gudang_mutasi');
        Schema::dropIfExists('gudang_barang');
        Schema::dropIfExists('gudang_lokasi');
    }
};
