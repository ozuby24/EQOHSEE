<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Authority — berkas kelayakan kerja seseorang.
 *
 * Menjawab satu pertanyaan yang ditanyakan pengawas di gerbang setiap
 * pagi: BOLEHKAH orang ini bekerja hari ini? Jawabannya bukan satu hal
 * melainkan tiga, dan ketiganya harus berlaku bersamaan:
 *
 *   kompetensi  — bersertifikat untuk pekerjaan yang akan dikerjakan
 *   MCU         — dinyatakan sehat untuk pekerjaan itu, masih berlaku
 *   kartu masuk — berizin memasuki area tambang, masih berlaku
 *
 * Ketiganya punya masa berlaku sendiri-sendiri dan kadaluarsa
 * sendiri-sendiri. Menyimpannya di tiga aplikasi berbeda — yang selama
 * ini terjadi — berarti tidak ada satu layar pun yang dapat menjawab
 * pertanyaan gerbang tadi.
 *
 * SATU ORANG, BANYAK SERTIFIKAT
 *
 * `ko_personnel` yang sudah ada menyimpan SATU sertifikat per orang:
 * satu kolom sertifikasi, satu nomor, satu tanggal kadaluarsa. Itu
 * bekerja sampai seorang pengawas memegang POP dan Ahli K3 Kebakaran
 * sekaligus — yang kedua tidak punya tempat, dan yang tercatat menjadi
 * tergantung siapa yang mengetik terakhir. Di sini relasinya satu ke
 * banyak, sebagaimana kenyataannya.
 *
 * JENIS KOMPETENSI ADALAH DAFTAR PILIH, BUKAN KETIK BEBAS
 *
 * 51 kompetensi mengikuti SK Dirjen 185.K/37.04/DJB/2019. Ketik bebas
 * adalah sebab utama satu kompetensi tercatat sebagai lima nama
 * berbeda — "POP", "P.O.P", "Pengawas Operasional Pertama", "pop",
 * "POP Madya" — lalu tidak satu pun terhitung pada rekap yang
 * mengelompokkan menurut nama.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ── master jenis kompetensi ──

           TIDAK bermilik perusahaan: daftar kompetensi berasal dari
           regulasi nasional, sama bagi setiap perusahaan. Perusahaan
           yang perlu menambah jenisnya sendiri memakai kolom
           company_id — yang NULL berarti milik bersama, pola yang
           sudah dipakai kursus dan template inspeksi. */
        Schema::create('kompetensi_jenis', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('nama');
            $t->string('lembaga')->nullable();      // BNSP, ESDM, KEMENAKER, KEMENKES, BASARNAS
            $t->string('klasifikasi', 10)->nullable();  // PO | PT | TTK — bila jenisnya memang khas satu golongan
            $t->unsignedSmallInteger('berlaku_bulan')->nullable();  // masa berlaku lazimnya
            $t->boolean('aktif')->default(true);
            $t->unsignedSmallInteger('urutan')->default(0);
            $t->timestamps();

            $t->unique(['company_id', 'nama'], 'kompetensi_jenis_unik');
            $t->index('aktif');
        });

        /* ── paspor: satu baris per orang ── */
        Schema::create('paspor', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            /* Ditautkan ke akun BILA orangnya punya akun. Banyak yang
               tidak — pekerja mitra, operator harian — dan mewajibkan
               akun akan membuat mereka tidak dapat dicatat sama sekali. */
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('nomor_register')->nullable();
            $t->string('nama');
            $t->string('nik')->nullable();
            $t->string('jabatan')->nullable();
            $t->string('departemen')->nullable();

            // PO = Pengawas Operasional · PT = Pengawas Teknis · TTK = Tenaga Teknik Khusus
            $t->string('klasifikasi', 10)->nullable();

            $t->string('status')->default('aktif');   // aktif | cuti | keluar
            $t->date('tgl_bergabung')->nullable();
            $t->string('foto')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'nik'], 'paspor_nik_unik');
            $t->index(['company_id', 'status']);
        });

        /* ── sertifikat kompetensi: banyak per orang ── */
        Schema::create('paspor_sertifikat', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paspor_id')->constrained('paspor')->cascadeOnDelete();
            $t->foreignId('kompetensi_jenis_id')->nullable()
              ->constrained('kompetensi_jenis')->nullOnDelete();

            /* Nama disalin saat penerbitan, bukan hanya dirujuk. Jenis
               yang kemudian diganti namanya tidak boleh mengubah bunyi
               sertifikat yang sudah tercetak dan sudah diperiksa
               inspektur. */
            $t->string('nama');
            $t->string('lembaga')->nullable();
            $t->string('nomor')->nullable();
            $t->date('tgl_terbit')->nullable();

            /* Masa berlaku PER SERTIFIKAT — inilah pokok perubahannya.
               Sebelumnya satu tanggal untuk seluruh sertifikat seseorang,
               sehingga yang paling cepat kadaluarsa menyeret sisanya. */
            $t->date('tgl_expired')->nullable();

            $t->string('berkas')->nullable();
            $t->text('catatan')->nullable();

            /* Sertifikat yang terbit dari LMS ditautkan, bukan disalin
               ulang. Tanpa tautan ini pelatihan internal yang sudah
               lulus akan diketik lagi tangan, dan dua salinannya
               berbeda pada bulan ketiga. */
            $t->foreignId('certificate_id')->nullable()
              ->constrained('certificates')->nullOnDelete();

            $t->timestamps();

            $t->index(['paspor_id', 'tgl_expired']);
            $t->index('tgl_expired');
        });

        /* ── MCU: pemeriksaan kesehatan berkala ── */
        Schema::create('paspor_mcu', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paspor_id')->constrained('paspor')->cascadeOnDelete();

            $t->date('tgl_periksa');
            $t->date('tgl_expired')->nullable();
            $t->string('penyelenggara')->nullable();   // klinik / rumah sakit pemeriksa
            $t->string('jenis')->default('Berkala');   // Awal | Berkala | Khusus | Purna

            /* Hasilnya, bukan angkanya. Rincian medis tidak disimpan di
               sini — itu rekam medis, punya aturan kerahasiaannya
               sendiri, dan tidak boleh terbaca seluruh admin HSE.
               Yang dicatat hanya kesimpulan kelayakan kerjanya. */
            $t->string('hasil')->default('Fit');       // Fit | Fit With Note | Unfit | Temporary Unfit
            $t->text('pembatasan')->nullable();        // mis. tidak boleh bekerja di ketinggian

            $t->string('berkas')->nullable();
            $t->timestamps();

            $t->index(['paspor_id', 'tgl_expired']);
            $t->index('tgl_expired');
        });

        /* ── kartu masuk tambang (SIMPER / mine permit) ── */
        Schema::create('paspor_kartu', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paspor_id')->constrained('paspor')->cascadeOnDelete();

            $t->string('jenis')->default('ID Card');   // ID Card | SIMPER | Mine Permit | Visitor
            $t->string('nomor')->nullable();
            $t->date('tgl_terbit')->nullable();
            $t->date('tgl_expired')->nullable();

            /* Kartu mengemudi menyebutkan golongan kendaraan yang boleh
               dikemudikan. Kartu masuk biasa tidak — kolomnya kosong,
               bukan diisi tanda hubung. */
            $t->string('golongan')->nullable();        // mis. LV, DT, Alat Berat
            $t->string('area')->nullable();            // area yang boleh dimasuki

            $t->string('berkas')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['paspor_id', 'tgl_expired']);
            $t->index('tgl_expired');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paspor_kartu');
        Schema::dropIfExists('paspor_mcu');
        Schema::dropIfExists('paspor_sertifikat');
        Schema::dropIfExists('paspor');
        Schema::dropIfExists('kompetensi_jenis');
    }
};
