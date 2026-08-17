<?php

use App\Support\Alur;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daftar acuan SPIP dan riwayat uji kelayakannya.
 *
 * DUA KEKURANGAN YANG DIPERBAIKI DI SINI.
 *
 * Pertama, jenis SPIP selama ini diketik bebas. "Dump Truck", "Dumptruck",
 * dan "DT" menjadi tiga jenis berbeda di mata sistem, sehingga rekap per
 * jenis tidak pernah dapat dipercaya dan penyaringan selalu kehilangan
 * sebagian barisnya. D'Best memecahkannya dengan master_unitkelayakans —
 * daftar yang dipelihara pemakainya sendiri, bukan daftar tetap dari
 * peraturan — dan bentuk itu yang diikuti di sini.
 *
 * Kedua, dan lebih berat: satu unit hanya menyimpan SATU tanggal
 * sertifikasi, di kolom ko_objects.tgl_sertifikasi. Setiap kali unitnya
 * diuji ulang, tanggal itu ditimpa dan uji sebelumnya hilang tanpa
 * jejak. Yang hilang bukan sekadar riwayat: ketika inspektur menanyakan
 * bukti bahwa sebuah alat angkat sudah diuji tiga tahun berturut-turut,
 * yang dapat ditunjukkan hanya yang terakhir.
 *
 * KOLOM LAMA TIDAK DIBUANG. ko_objects.tgl_sertifikasi tetap menjadi
 * "keadaan sekarang" yang dibaca Ko::status(), dan uji kelayakan yang
 * DISETUJUI memperbaruinya. Membongkar seluruh perhitungan status yang
 * sudah dipakai lima halaman hanya untuk memindahkan satu tanggal adalah
 * risiko yang tidak sebanding — dan selisih antara keduanya justru
 * ditampilkan sebagai temuan, bukan disembunyikan.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ── daftar acuan jenis unit SPIP ── */
        Schema::create('ko_unit_master', function (Blueprint $t) {
            $t->id();

            /* NULL = milik bersama, terlihat semua perusahaan. Daftar ini
               dipelihara pemakainya, jadi tiap perusahaan boleh menambah
               jenisnya sendiri tanpa mengubah acuan bersama. */
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('kode');
            $t->string('unit');                       // nama jenis unit
            $t->string('kategori')->nullable();       // Sarana | Prasarana | Instalasi | Peralatan

            /* Interval uji baku untuk jenis ini. Dipakai mengusulkan
               tanggal kadaluarsa saat uji dicatat — diusulkan, tidak
               dipaksakan, sebab lembaga uji dapat menetapkan lain. */
            $t->unsignedTinyInteger('interval_tahun')->default(1);

            $t->text('keterangan')->nullable();
            $t->boolean('aktif')->default(true);
            $t->unsignedSmallInteger('urutan')->default(0);

            $t->timestamps();

            /* Kode unik PER PEMILIK, bukan unik global: dua perusahaan
               boleh sama-sama memakai kode "DT" untuk daftarnya
               sendiri. Unik global akan membuat perusahaan kedua gagal
               menyimpan tanpa sebab yang masuk akal baginya. */
            $t->unique(['company_id', 'kode']);
            $t->index(['aktif', 'urutan']);
        });

        /* ── riwayat uji kelayakan ── */
        Schema::create('ko_uji_kelayakan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ko_object_id')->constrained('ko_objects')->cascadeOnDelete();

            /* Perusahaan yang mengoperasikan unit saat diuji — padanan
               corps_id di D'Best. Disimpan terpisah dari pemilik unitnya
               karena alat sewa berpindah kontraktor, dan uji yang lama
               tetap milik kontraktor yang memakainya waktu itu. */
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('nomor')->nullable();

            /* Merk, tipe, dan nomor seri DISALIN saat pengujian, tidak
               hanya dirujuk ke unitnya. Unit yang kemudian diganti
               mesinnya atau dikoreksi datanya tidak boleh mengubah bunyi
               sertifikat yang sudah terbit dan sudah diperiksa
               inspektur. */
            $t->string('merk')->nullable();
            $t->string('tipe')->nullable();
            $t->string('nomor_seri')->nullable();

            $t->date('tgl_inspeksi');
            $t->date('tgl_expired')->nullable();

            $t->string('pemeriksa')->nullable();      // nama pemeriksa
            $t->string('lembaga')->nullable();        // lembaga penguji
            $t->string('lokasi_uji')->nullable();

            $t->string('hasil')->default('Layak');    // Layak | Layak Bersyarat | Tidak Layak
            $t->text('syarat')->nullable();           // syarat bila Layak Bersyarat
            $t->text('temuan')->nullable();
            $t->text('rekomendasi')->nullable();
            $t->string('berkas')->nullable();

            $t->string('status')->default(Alur::DRAF);
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->timestamps();

            $t->index(['ko_object_id', 'tgl_inspeksi']);
            $t->index(['company_id', 'status']);
            $t->index('tgl_expired');
        });

        /* Tautan unit ke daftar acuannya. Nullable: unit yang sudah
           terlanjur ada belum tentu cocok dengan salah satu baris master,
           dan memaksanya cocok berarti menebak — tebakan yang lalu
           tersimpan sebagai fakta. */
        Schema::table('ko_objects', function (Blueprint $t) {
            $t->foreignId('ko_unit_master_id')->nullable()->after('jenis')
              ->constrained('ko_unit_master')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ko_objects', function (Blueprint $t) {
            $t->dropConstrainedForeignId('ko_unit_master_id');
        });

        Schema::dropIfExists('ko_uji_kelayakan');
        Schema::dropIfExists('ko_unit_master');
    }
};
