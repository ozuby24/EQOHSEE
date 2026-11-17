<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Miners — Safe Track: MCU, Mine Permit, dan SIMPER.
 *
 * Skema ini menggantikan rangkaian `paspor_*` yang lama dan mengikuti
 * Project1 (BaraSafety Safe Track), tempat ketiga dokumen itu sudah
 * dipakai sungguhan di lapangan.
 *
 * SATU RANTAI, BUKAN TIGA DAFTAR TERPISAH — dan inilah yang paling
 * salah pada skema lama. Ketiganya BERURUTAN:
 *
 *     MCU menentukan Mine Permit, Mine Permit menentukan SIMPER.
 *
 * Karena itu tiap dokumen di bawah menyimpan kunci dokumen sebelumnya,
 * bukan sekadar menunjuk orangnya. Mine Permit yang hanya menunjuk
 * pekerja tidak dapat menjawab "hasil MCU mana yang menjadi dasar kartu
 * ini" — pertanyaan pertama yang diajukan Inspektur Tambang ketika
 * sebuah kartu dipersoalkan, dan pertanyaan yang tidak dapat dijawab
 * ulang sesudah MCU berikutnya terbit.
 *
 * TIGA GENERASI DI PROJECT1, YANG DIBAWA HANYA YANG TERBARU.
 *
 * Di sana jalur SIMPER pernah ditulis dua kali — `simpers`/`cardsims`
 * (2022) lalu `form_simpers` (2025) — dan jalur induksi juga dua kali.
 * Yang hidup dan tersambung ke rantai MCU→Permit→SIMPER adalah yang
 * 2025; sisanya tinggal sebagai tabel yang tidak lagi ditulisi. Membawa
 * keduanya ke sini berarti membawa dua sumber kebenaran untuk satu
 * dokumen, dan yang membaca berikutnya tidak punya cara memilih.
 *
 * `corps` TIDAK DIBAWA. Isinya daftar perusahaan — IUP dan IUJP beserta
 * hubungan induk-anaknya — dan EQOHSEE sudah punya `companies` yang
 * menyimpan persis itu, lengkap dengan `parent_id`. Tabel perusahaan
 * kedua berarti dua daftar yang harus diubah bersama setiap kali ada
 * mitra baru, dan yang tertinggal tidak menimbulkan galat: hanya kartu
 * yang terbit atas nama perusahaan yang tidak ada di layar sebelah.
 *
 * ACUAN — jangan diubah tanpa memeriksa teks resminya:
 *   · 001-SPM-007 Standar Mine Permit/KIMPER
 *   · 007-SOP-OHSE Penerbitan Mine Permit, ID, dan SIMPER
 *   Keduanya ada di Project1 pada docs/sop/. Bila perilaku aplikasi
 *   berbeda dari SOP, SOP yang menang.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->master();
        $this->pekerja();
        $this->mcu();
        $this->induksi();
        $this->permit();
        $this->simper();
        $this->kompetensi();
    }

    /* ═══════════════════ MASTER ═══════════════════
     *
     * Seluruhnya BERKOLOM company_id dan boleh kosong. Berbeda dari
     * master Investigasi — yang isinya kerangka regulasi nasional dan
     * karena itu tanpa pemilik — daftar di bawah ini milik masing-masing
     * tambang: nama departemen, blok, dan jenis unit berbeda antar
     * perusahaan. Baris tanpa pemilik (company_id NULL) menjadi daftar
     * awal bersama yang dipakai perusahaan yang belum menyusun
     * daftarnya sendiri.
     *
     * SELURUHNYA BERKOLOM `aktif`, TANPA KECUALI — dan itu bukan
     * keseragaman demi keseragaman. Kesembilan daftar dibaca lewat
     * App\Models\Miners\Master::scopeTerpakai(), yang menyaring
     * `aktif`. Satu tabel yang tidak punya kolom itu gagal DUA CARA
     * BERBEDA: MySQL menolaknya sebagai "Unknown column" — galat 500
     * di hadapan pengguna — sedangkan SQLite memperlakukan
     * `"aktif"` sebagai untaian teks biasa, sehingga kuerinya BERHASIL
     * dan menjawab nol baris. Yang kedua itu yang berbahaya: daftar
     * pilihnya kosong, ujinya hijau, dan tidak ada satu pun galat yang
     * menunjuk ke sebabnya.
     */
    private function master(): void
    {
        Schema::create('mnr_departemen', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            /* Kunci tetap daftar awal bersama — lihat catatan panjang
               pada App\Support\Miners\MasterMiners. NULL bagi baris
               yang dibuat perusahaan sendiri. */
            $t->string('kunci', 60)->nullable()->index();
            $t->string('nama', 120);
            $t->string('kode', 20)->nullable();
            $t->unsignedSmallInteger('urutan')->default(100);
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
        });

        Schema::create('mnr_jabatan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            /* Kunci tetap daftar awal bersama — lihat catatan panjang
               pada App\Support\Miners\MasterMiners. NULL bagi baris
               yang dibuat perusahaan sendiri. */
            $t->string('kunci', 60)->nullable()->index();
            $t->string('nama', 120);
            $t->unsignedSmallInteger('urutan')->default(100);
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
        });

        /* Mitra yang dipekerjakan IUJP di bawah IUP. Terpisah dari
           `companies`: subkontraktor tidak memegang izin sendiri dan
           tidak pernah menjadi penyewa aplikasi — mencatatnya sebagai
           perusahaan berarti ia muncul pada tiap penyaring perusahaan
           di seluruh aplikasi. */
        Schema::create('mnr_subkontraktor', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('nama', 150);
            $t->string('kode', 20)->nullable();
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
        });

        Schema::create('mnr_blok', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            /* Kunci tetap daftar awal bersama — lihat catatan panjang
               pada App\Support\Miners\MasterMiners. NULL bagi baris
               yang dibuat perusahaan sendiri. */
            $t->string('kunci', 60)->nullable()->index();
            $t->string('nama', 120);
            $t->unsignedSmallInteger('urutan')->default(100);
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
        });

        Schema::create('mnr_sub_blok', function (Blueprint $t) {
            $t->id();
            /* BERKOLOM PEMILIK SENDIRI, tidak menumpang induknya.
               Induknya — daftar lokasi kerja dan golongan unit — adalah
               acuan BERSAMA dan karena itu tanpa pemilik, sehingga
               batas perusahaan yang menumpang ke sana tidak membatasi
               apa pun: rincian yang ditambahkan satu tambang akan
               terlihat oleh seluruh tambang lain, dan kebocorannya
               tidak berbunyi. */
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('blok_id')->constrained('mnr_blok')->cascadeOnDelete();
            $t->string('nama', 120);
            $t->unsignedSmallInteger('urutan')->default(100);
            $t->timestamps();

            $t->index(['company_id']);
        });

        /* Golongan kendaraan (LV, DT, Excavator …) beserta rinciannya.
           Dua tingkat, sama seperti Project1: SIMPER diberikan per
           GOLONGAN, sedangkan uji praktiknya dilakukan pada unit
           tertentu di dalam golongan itu. */
        Schema::create('mnr_kendaraan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            /* Kunci tetap daftar awal bersama — lihat catatan panjang
               pada App\Support\Miners\MasterMiners. NULL bagi baris
               yang dibuat perusahaan sendiri. */
            $t->string('kunci', 60)->nullable()->index();
            $t->string('nama', 120);
            $t->string('kode', 20)->nullable();

            /* Kelas SIMPOL yang dituntut golongan ini — A, B1, B2 Umum.
               SOP 001-SPM-007 mensyaratkan kecocokan unit dengan kelas
               SIM; disimpan di sini supaya pemeriksaannya tidak perlu
               ditulis ulang sebagai daftar di dalam kode. */
            $t->string('kelas_simpol', 20)->nullable();

            /* Golongan yang menuntut Surat Izin Operator tersendiri —
               crane, dan alat angkat lain. */
            $t->boolean('wajib_sio')->default(false);

            $t->unsignedSmallInteger('urutan')->default(100);
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
        });

        Schema::create('mnr_sub_kendaraan', function (Blueprint $t) {
            $t->id();
            /* BERKOLOM PEMILIK SENDIRI, tidak menumpang induknya.
               Induknya — daftar lokasi kerja dan golongan unit — adalah
               acuan BERSAMA dan karena itu tanpa pemilik, sehingga
               batas perusahaan yang menumpang ke sana tidak membatasi
               apa pun: rincian yang ditambahkan satu tambang akan
               terlihat oleh seluruh tambang lain, dan kebocorannya
               tidak berbunyi. */
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('kendaraan_id')->constrained('mnr_kendaraan')->cascadeOnDelete();

            /* Kunci tetap daftar awal bersama — lihat catatan panjang
               pada App\Support\Miners\MasterMiners. NULL bagi baris
               yang dibuat perusahaan sendiri. */
            $t->string('kunci', 60)->nullable()->index();
            $t->string('nama', 120);
            $t->timestamps();

            $t->index(['company_id']);
        });

        /* Jenis unit yang boleh dikemudikan — daftar rata, dipakai pada
           baris unit SIMPER. Di Project1 bernama `allunits`. */
        Schema::create('mnr_jenis_unit', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            /* Kunci tetap daftar awal bersama — lihat catatan panjang
               pada App\Support\Miners\MasterMiners. NULL bagi baris
               yang dibuat perusahaan sendiri. */
            $t->string('kunci', 60)->nullable()->index();
            $t->string('nama', 150);
            $t->unsignedSmallInteger('urutan')->default(100);
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
        });

        /* Tipe permit (Full / Temporary / Visitor) dan kategorinya.
           SOP menetapkan masa berlaku berbeda per tipe — Visitor 7 hari,
           Temporary paling lama 1 bulan — jadi lamanya disimpan di sini
           alih-alih ditulis sebagai cabang di dalam kode. */
        Schema::create('mnr_tipe_permit', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            /* Kunci tetap daftar awal bersama — lihat catatan panjang
               pada App\Support\Miners\MasterMiners. NULL bagi baris
               yang dibuat perusahaan sendiri. */
            $t->string('kunci', 60)->nullable()->index();
            $t->string('nama', 60);

            /* Berapa hari berlaku sejak terbit. NULL berarti mengikuti
               aturan tahunan: berlaku sampai 31 Desember tahun terbit. */
            $t->unsignedSmallInteger('hari_berlaku')->nullable();

            $t->unsignedSmallInteger('urutan')->default(100);
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
        });

        Schema::create('mnr_kategori_permit', function (Blueprint $t) {
            $t->id();
            /* Berkolom pemilik sendiri, dengan alasan yang sama seperti
               mnr_sub_blok dan mnr_sub_kendaraan: induknya adalah acuan
               bersama tanpa pemilik, sehingga batas yang menumpang ke
               sana tidak membatasi apa pun. */
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('tipe_permit_id')->constrained('mnr_tipe_permit')->cascadeOnDelete();

            /* Kunci tetap daftar awal bersama — lihat catatan panjang
               pada App\Support\Miners\MasterMiners. NULL bagi baris
               yang dibuat perusahaan sendiri. */
            $t->string('kunci', 60)->nullable()->index();
            $t->string('nama', 120);
            $t->timestamps();

            $t->index(['company_id']);
        });

        /* Hasil MCU beserta NILAINYA. Angka itu yang menentukan boleh
           tidaknya permit terbit; disimpan sebagai data, bukan sebagai
           daftar teks di dalam kode, supaya klinik yang memakai istilah
           berbeda ("Fit With Note" lawan "Fit Dengan Catatan") tidak
           menuntut perubahan kode. */
        Schema::create('mnr_hasil_mcu', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            /* Kunci tetap daftar awal bersama — lihat catatan panjang
               pada App\Support\Miners\MasterMiners. NULL bagi baris
               yang dibuat perusahaan sendiri. */
            $t->string('kunci', 60)->nullable()->index();
            $t->string('nama', 120);

            /* 0..100. Ambang kelayakan dibaca dari sini. */
            $t->unsignedTinyInteger('nilai')->default(0);

            /* Hasil yang MEMBOLEHKAN permit terbit. Diturunkan dari
               nilai akan salah pada "Fit With Note": nilainya di bawah
               Fit penuh, tetapi orangnya tetap boleh bekerja. */
            $t->boolean('layak')->default(false);

            $t->unsignedSmallInteger('urutan')->default(100);
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
        });

        /* Penanggung Jawab Operasional mitra kerja — pintu pengajuan
           menurut SOP. Berkas mitra masuk lewat PJO, bukan lewat
           pekerjanya sendiri. */
        Schema::create('mnr_pjo', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('nama', 150);
            $t->string('jabatan', 120)->nullable();
            $t->string('email', 150)->nullable();
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
        });
    }

    /* ═══════════════════ PEKERJA ═══════════════════ */

    private function pekerja(): void
    {
        /* Satu baris per orang, dan identitasnya berhenti di sini.
           Di Project1 tabel ini bernama `inductions` — nama yang
           menyesatkan sejak awal, sebab isinya bukan induksi melainkan
           orangnya; induksinya sendiri tercatat pada rangkaian
           `induction_registrations`. */
        Schema::create('mnr_pekerja', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('no_registrasi', 40)->nullable()->index();
            $t->string('nama', 150);
            $t->string('nik', 40)->nullable()->index();

            /* Nomor induk karyawan menurut perusahaannya — berbeda dari
               NIK kependudukan, dan keduanya dipakai: yang satu untuk
               mencocokkan dengan KTP, yang lain untuk absensi. */
            $t->string('no_induk', 40)->nullable()->index();

            $t->date('tanggal_lahir')->nullable();
            $t->string('gol_darah', 5)->nullable();
            $t->string('telepon', 30)->nullable();
            $t->string('telepon_darurat', 30)->nullable();

            $t->foreignId('departemen_id')->nullable()->constrained('mnr_departemen')->nullOnDelete();
            $t->foreignId('jabatan_id')->nullable()->constrained('mnr_jabatan')->nullOnDelete();
            $t->foreignId('subkontraktor_id')->nullable()->constrained('mnr_subkontraktor')->nullOnDelete();
            $t->foreignId('blok_id')->nullable()->constrained('mnr_blok')->nullOnDelete();
            $t->foreignId('sub_blok_id')->nullable()->constrained('mnr_sub_blok')->nullOnDelete();

            /* karyawan | kontrak | harian | tamu */
            $t->string('status_kerja', 20)->nullable();

            /* aktif | resign | nonaktif */
            $t->string('status', 20)->default('aktif')->index();
            $t->date('tanggal_resign')->nullable();

            $t->string('foto', 500)->nullable();
            $t->string('berkas_ktp', 500)->nullable();
            $t->text('catatan')->nullable();

            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['company_id', 'status']);
        });
    }

    /* ═══════════════════ MCU ═══════════════════ */

    private function mcu(): void
    {
        /* Satu surat pengantar ke klinik, memuat banyak orang. Itulah
           bentuk aslinya di lapangan: perusahaan mengirim satu surat
           berisi daftar nama, bukan satu surat per orang. */
        Schema::create('mnr_mcu', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('no_registrasi', 40)->nullable()->index();
            $t->date('tanggal')->nullable();

            /* Klinik atau rumah sakit yang dituju. */
            $t->string('kepada', 200)->nullable();
            $t->text('perihal')->nullable();
            $t->text('catatan')->nullable();

            /* draf | diajukan | diperiksa | disetujui | ditolak */
            $t->string('status', 20)->default('draf')->index();

            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['company_id', 'tanggal']);
        });

        Schema::create('mnr_mcu_orang', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mcu_id')->constrained('mnr_mcu')->cascadeOnDelete();
            $t->foreignId('pekerja_id')->nullable()->constrained('mnr_pekerja')->nullOnDelete();

            /* Identitas disalin, bukan hanya ditunjuk. Surat MCU yang
               sudah dikirim ke klinik tidak boleh berubah isinya ketika
               nama atau jabatan orangnya disunting setahun kemudian —
               yang dipegang klinik adalah kertas, dan kertas itu tidak
               ikut berubah. */
            $t->string('nama', 150);
            $t->string('nik', 40)->nullable();
            $t->string('jabatan', 120)->nullable();
            $t->unsignedTinyInteger('usia')->nullable();

            $t->foreignId('departemen_id')->nullable()->constrained('mnr_departemen')->nullOnDelete();
            $t->foreignId('hasil_id')->nullable()->constrained('mnr_hasil_mcu')->nullOnDelete();

            /* Tanggal pelaksanaan dan tanggal MCU berikutnya. */
            $t->date('tanggal_periksa')->nullable();
            $t->date('tanggal_berikut')->nullable();

            /* Masa berlaku hasil — satu tahun sejak pelaksanaan menurut
               SOP. Disimpan tersendiri dan tidak dihitung ulang tiap
               kali dibaca: aturan masa berlakunya dapat berubah, dan
               kartu yang sudah terbit harus tetap dapat menunjukkan
               dasar tanggalnya. */
            $t->date('berlaku_sampai')->nullable()->index();

            $t->string('berkas_hasil', 500)->nullable();
            $t->string('berkas_rekomendasi', 500)->nullable();

            /* Hasil uji alkohol dan narkoba — lampiran wajib menurut
               SOP, dan tidak punya kolom sama sekali di Project1. */
            $t->string('hasil_napza', 20)->nullable();
            $t->string('berkas_napza', 500)->nullable();

            /* Butir yang menuntut pemeriksaan lanjutan. Tanggalnya
               tenggat rujukan, bukan tanggal terbit. */
            $t->date('tenggat_rujukan')->nullable();

            $t->boolean('aktif')->default(true);
            $t->date('tanggal_nonaktif')->nullable();

            $t->text('catatan')->nullable();
            $t->text('catatan_mitra')->nullable();
            $t->timestamps();

            $t->index(['mcu_id', 'pekerja_id']);
        });

        Schema::create('mnr_mcu_rujukan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mcu_orang_id')->constrained('mnr_mcu_orang')->cascadeOnDelete();
            $t->date('tanggal_surat')->nullable();
            $t->string('dokter', 150)->nullable();
            $t->string('poliklinik', 150)->nullable();
            $t->string('rumah_sakit', 200)->nullable();
            $t->text('diagnosis_awal')->nullable();
            $t->text('keterangan')->nullable();
            $t->string('berkas', 500)->nullable();
            $t->timestamps();
        });
    }

    /* ═══════════════════ INDUKSI ═══════════════════ */

    private function induksi(): void
    {
        Schema::create('mnr_induksi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('no_registrasi', 40)->nullable()->index();
            $t->date('tanggal')->nullable();
            $t->text('perihal')->nullable();
            $t->string('berkas_permohonan', 500)->nullable();

            $t->string('status', 20)->default('draf')->index();

            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['company_id', 'tanggal']);
        });

        Schema::create('mnr_induksi_orang', function (Blueprint $t) {
            $t->id();
            $t->foreignId('induksi_id')->constrained('mnr_induksi')->cascadeOnDelete();
            $t->foreignId('pekerja_id')->nullable()->constrained('mnr_pekerja')->nullOnDelete();

            /* Hasil MCU yang menjadi dasarnya. Inilah sambungan rantai
               pertama: tanpa MCU yang layak, induksi tidak boleh
               dijadwalkan. */
            $t->foreignId('mcu_orang_id')->nullable()->constrained('mnr_mcu_orang')->nullOnDelete();

            $t->date('tanggal_induksi')->nullable();
            $t->string('lokasi', 150)->nullable();

            /* Nilai post test dan percobaannya. SOP menuntut kelulusan
               dan membatasi remidi dua kali; keduanya tidak punya kolom
               sama sekali di Project1, sehingga aturannya tidak pernah
               dapat ditegakkan. */
            $t->unsignedTinyInteger('nilai')->nullable();
            $t->unsignedTinyInteger('percobaan')->default(1);

            $t->string('berkas_sertifikat', 500)->nullable();
            $t->string('berkas_hadir', 500)->nullable();

            /* belum | lulus | remidi | gagal */
            $t->string('status', 20)->default('belum')->index();

            $t->date('berlaku_sampai')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['induksi_id', 'pekerja_id']);
        });
    }

    /* ═══════════════════ MINE PERMIT ═══════════════════ */

    private function permit(): void
    {
        Schema::create('mnr_permit', function (Blueprint $t) {
            $t->id();

            /* IUP PENERBIT kartu. Terpisah dari perusahaan tempat
               orangnya bekerja: satu IUP menerbitkan kartu bagi pekerja
               belasan IUJP, dan kartu itu miliknya — yang mencabut,
               yang bertanggung jawab, dan yang namanya tercetak. */
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            /* IUJP tempat orangnya bekerja. Boleh kosong: pekerja IUP
               sendiri tidak punya kontraktor. */
            $t->foreignId('kontraktor_id')->nullable()->constrained('companies')->nullOnDelete();

            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();

            /* Dua dokumen yang menjadi dasarnya — rantai MCU → Induksi
               → Permit. Disimpan sebagai kunci, bukan disimpulkan dari
               tanggal: "hasil MCU mana yang mendasari kartu ini" adalah
               pertanyaan pertama saat sebuah kartu dipersoalkan, dan
               menyimpulkannya dari tanggal akan menjawab salah begitu
               MCU berikutnya terbit. */
            $t->foreignId('mcu_orang_id')->nullable()->constrained('mnr_mcu_orang')->nullOnDelete();
            $t->foreignId('induksi_orang_id')->nullable()->constrained('mnr_induksi_orang')->nullOnDelete();

            $t->string('no_registrasi', 40)->nullable()->index();
            $t->date('tanggal')->nullable();

            $t->foreignId('tipe_permit_id')->nullable()->constrained('mnr_tipe_permit')->nullOnDelete();
            $t->foreignId('kategori_permit_id')->nullable()->constrained('mnr_kategori_permit')->nullOnDelete();

            /* FULL | RESTRICTED | UNRESTRICTED — SOP butir 5.7. */
            $t->string('cakupan_area', 20)->nullable();

            /* putih | hijau | merah | biru — warna kartu per peruntukan. */
            $t->string('kode_warna', 10)->nullable();

            $t->string('foto', 500)->nullable();

            /* draf | diajukan | ohse | ktt | terbit | ditolak | dicabut */
            $t->string('status', 20)->default('draf')->index();

            $t->date('berlaku_sampai')->nullable()->index();

            /* tahunan | tipe | mcu | manual — DARI MANA tanggal di atas
               berasal. Tanpa kolom ini, tanggal yang dikunci petugas
               tidak dapat dibedakan dari tanggal yang dihitung sistem,
               dan penyegaran berikutnya menimpanya diam-diam. */
            $t->string('sumber_berlaku', 12)->nullable();

            $t->date('tanggal_cabut')->nullable();
            $t->string('alasan_cabut', 200)->nullable();

            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['company_id', 'status']);
        });

        /* Kelengkapan berkas. SATU BARIS PER JENIS LAMPIRAN, bukan satu
           kolom per lampiran seperti Project1: SOP merinci 9–12 lampiran
           yang berbeda per jenis pengajuan, dan menambah satu lampiran
           tidak boleh menuntut migrasi kolom baru. */
        Schema::create('mnr_permit_berkas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('permit_id')->constrained('mnr_permit')->cascadeOnDelete();

            /* Kunci lampiran — lihat App\Support\Miners\Lampiran. */
            $t->string('jenis', 40)->index();

            $t->string('berkas', 500)->nullable();
            $t->string('nomor', 120)->nullable();
            $t->date('tanggal')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['permit_id', 'jenis']);
        });
    }

    /* ═══════════════════ SIMPER ═══════════════════ */

    private function simper(): void
    {
        Schema::create('mnr_simper', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            /* SIMPER menempel pada PERMIT, bukan langsung pada orangnya.
               Permit dicabut berarti SIMPER ikut gugur, dan hubungan itu
               harus terbaca dari barisnya sendiri. */
            $t->foreignId('permit_id')->constrained('mnr_permit')->cascadeOnDelete();
            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();

            $t->string('no_simper', 40)->nullable()->index();
            $t->date('tanggal')->nullable();

            /* PR | F | R1 | R2 | I — lima kelas menurut SOP. */
            $t->string('kelas', 10)->nullable();

            /* SIM Kepolisian yang mendasarinya. Habis masa berlakunya,
               SIMPER otomatis tidak berlaku — karena itu tanggalnya
               disimpan di sini dan ikut dipantau. */
            $t->string('no_simpol', 40)->nullable();
            $t->string('jenis_simpol', 20)->nullable();
            $t->date('simpol_berlaku_sampai')->nullable()->index();
            $t->string('berkas_simpol', 500)->nullable();

            $t->string('berkas_permohonan', 500)->nullable();
            $t->string('berkas_ddt', 500)->nullable();
            $t->string('berkas_sio', 500)->nullable();
            $t->string('pengalaman_kerja', 150)->nullable();
            $t->string('email_atasan', 150)->nullable();

            $t->string('status', 20)->default('draf')->index();
            $t->date('berlaku_sampai')->nullable()->index();
            $t->string('sumber_berlaku', 12)->nullable();

            $t->text('catatan')->nullable();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['company_id', 'status']);
        });

        /* Unit yang boleh dikemudikan, beserta nilai ujinya. Satu SIMPER
           memuat beberapa baris; penambahan unit dan upgrade menambah
           baris di sini alih-alih menerbitkan kartu baru. */
        Schema::create('mnr_simper_unit', function (Blueprint $t) {
            $t->id();
            $t->foreignId('simper_id')->constrained('mnr_simper')->cascadeOnDelete();
            $t->foreignId('kendaraan_id')->nullable()->constrained('mnr_kendaraan')->nullOnDelete();
            $t->foreignId('jenis_unit_id')->nullable()->constrained('mnr_jenis_unit')->nullOnDelete();

            /* Kewenangan atas unit itu: operator | pengawas | trainer. */
            $t->string('kewenangan', 20)->nullable();

            $t->unsignedTinyInteger('nilai_p2h')->nullable();
            $t->unsignedTinyInteger('nilai_praktek')->nullable();
            $t->unsignedTinyInteger('nilai_teori')->nullable();
            $t->unsignedTinyInteger('nilai_rambu')->nullable();

            $t->string('berkas_teori', 500)->nullable();
            $t->string('berkas_rambu', 500)->nullable();
            $t->string('berkas_praktek', 500)->nullable();

            /* baru | penambahan | upgrade | perpanjangan — DARI MANA
               baris ini datang. Tanpa itu, kartu yang unitnya bertambah
               tiga kali tidak dapat menjelaskan kapan dan lewat
               pengajuan mana tiap unitnya masuk. */
            $t->string('asal', 20)->default('baru');

            $t->timestamps();

            $t->index(['simper_id', 'kendaraan_id']);
        });

        /* ── PENGAJUAN LANJUTAN ──
         *
         * Tiga jenis — penambahan unit, upgrade kelas, perpanjangan —
         * pada SATU tabel, dibedakan kolom `jenis`. Project1 menulisnya
         * sebagai tiga rangkaian tabel terpisah yang isinya hampir
         * sama; akibatnya tiap perubahan alur dikerjakan tiga kali, dan
         * yang tertinggal adalah yang paling jarang dibuka.
         */
        Schema::create('mnr_simper_ajuan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('simper_id')->constrained('mnr_simper')->cascadeOnDelete();

            /* penambahan | upgrade | perpanjangan */
            $t->string('jenis', 20)->index();

            $t->string('no_registrasi', 40)->nullable();
            $t->date('tanggal')->nullable();

            /* Perpanjangan membawa SIMPOL baru; penambahan dan upgrade
               tidak. Dibiarkan kosong bagi keduanya. */
            $t->string('berkas_simpol', 500)->nullable();
            $t->date('simpol_berlaku_sampai')->nullable();

            $t->string('berkas_permohonan', 500)->nullable();
            $t->string('berkas_evaluasi', 500)->nullable();
            $t->string('pengalaman_kerja', 150)->nullable();

            $t->string('status', 20)->default('draf')->index();
            $t->text('catatan')->nullable();

            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('mnr_simper_ajuan_unit', function (Blueprint $t) {
            $t->id();
            $t->foreignId('ajuan_id')->constrained('mnr_simper_ajuan')->cascadeOnDelete();
            $t->foreignId('kendaraan_id')->nullable()->constrained('mnr_kendaraan')->nullOnDelete();
            $t->foreignId('jenis_unit_id')->nullable()->constrained('mnr_jenis_unit')->nullOnDelete();

            $t->string('kewenangan', 20)->nullable();
            $t->unsignedTinyInteger('nilai_p2h')->nullable();
            $t->unsignedTinyInteger('nilai_praktek')->nullable();
            $t->unsignedTinyInteger('nilai_teori')->nullable();
            $t->unsignedTinyInteger('nilai_rambu')->nullable();

            $t->string('berkas_teori', 500)->nullable();
            $t->string('berkas_rambu', 500)->nullable();
            $t->timestamps();
        });

        /* ── ALUR PERSETUJUAN, SATU TABEL UNTUK SELURUH DOKUMEN ──
         *
         * Project1 punya SEMBILAN tabel alur yang isinya identik —
         * mcu_flows, induction_flows, mine_permit_flows, form_simper_flows,
         * workflows, workflowadds, workflowexps, workflowextends,
         * flowsimpers — masing-masing dengan seq dan lastaction yang
         * sama persis. Sembilan salinan satu aturan berarti perubahan
         * alur dikerjakan sembilan kali, dan yang tertinggal tidak
         * menimbulkan galat: hanya satu jenis dokumen yang diam-diam
         * berhenti pada langkah yang salah.
         */
        Schema::create('mnr_alur', function (Blueprint $t) {
            $t->id();

            /* mcu | induksi | permit | simper | ajuan */
            $t->string('dokumen', 20);
            $t->unsignedBigInteger('dokumen_id');

            $t->unsignedSmallInteger('urutan')->default(1);

            /* pjo | dokter | ohse | ktt — peran yang harus bertindak. */
            $t->string('peran', 20);

            /* menunggu | setuju | tolak | dikembalikan */
            $t->string('keadaan', 20)->default('menunggu')->index();

            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->dateTime('bertindak_pada')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['dokumen', 'dokumen_id', 'urutan']);
        });
    }

    public function down(): void
    {
        foreach ([
            'mnr_alur',
            'mnr_simper_ajuan_unit', 'mnr_simper_ajuan',
            'mnr_simper_unit', 'mnr_simper',
            'mnr_permit_berkas', 'mnr_permit',
            'mnr_induksi_orang', 'mnr_induksi',
            'mnr_mcu_rujukan', 'mnr_mcu_orang', 'mnr_mcu',
            'mnr_pekerja',
            'mnr_pjo', 'mnr_hasil_mcu',
            'mnr_kategori_permit', 'mnr_tipe_permit',
            'mnr_jenis_unit', 'mnr_sub_kendaraan', 'mnr_kendaraan',
            'mnr_sub_blok', 'mnr_blok',
            'mnr_subkontraktor', 'mnr_jabatan', 'mnr_departemen',
        ] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }

    /* ═══════════════════ KOMPETENSI ═══════════════════ */

    /**
     * Sertifikat kompetensi pertambangan — POP, POM, POU, Juru Ukur, dst.
     *
     * TIDAK BERASAL DARI PROJECT1, dan itu disengaja. Modul Safe Track
     * di sana tidak menyimpan sertifikat kompetensi sama sekali;
     * register ini tumbuh di EQOHSEE sendiri, di atas master 51 jenis
     * menurut SK Dirjen Minerba 185.K/37.04/DJB/2019 yang sudah ada di
     * `kompetensi_jenis`.
     *
     * Dibawa serta karena membangun ulang modul ini menurut Project1
     * tidak berarti MEMBUANG yang tidak ada di sana: sertifikat
     * kompetensi adalah dokumen yang diminta Inspektur Tambang, punya
     * masa berlaku sendiri, dan sudah tersambung ke tiga tempat lain —
     * ubin dasbor, grafik masa berlaku, dan registri berkas. Dihapus
     * diam-diam, yang hilang bukan satu layar melainkan satu kewajiban.
     *
     * MASA BERLAKUNYA MELEKAT PADA SERTIFIKATNYA, bukan pada orangnya.
     * Seorang pengawas dapat memegang POP sampai 2028 dan Ahli K3
     * Kebakaran yang habis bulan depan; satu tanggal bagi keduanya
     * membuat salah satunya selalu salah.
     */
    private function kompetensi(): void
    {
        Schema::create('mnr_kompetensi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();

            /* Jenisnya dari daftar pilih, bukan ketik bebas. Sebagai
               teks bebas, satu kompetensi yang sama tercatat sebagai
               "POP", "P.O.P", "Pengawas Operasional Pertama", dan
               "pop" — lalu rekap yang mengelompokkan menurut nama
               menghitungnya sebagai empat kompetensi berbeda, masing-
               masing satu orang, dan tidak satu pun mencapai jumlah
               minimum yang dituntut regulasi. Angkanya salah tanpa satu
               galat pun.

               Tetap boleh kosong: sertifikat yang jenisnya belum ada di
               master harus tetap dapat dicatat hari ini, bukan ditunda
               sampai seseorang menambah masternya. */
            $t->foreignId('kompetensi_jenis_id')->nullable()
                ->constrained('kompetensi_jenis')->nullOnDelete();

            $t->string('nama', 200);
            $t->string('lembaga', 150)->nullable();
            $t->string('nomor', 100)->nullable();
            $t->date('tanggal_terbit')->nullable();
            $t->date('berlaku_sampai')->nullable();
            $t->string('berkas')->nullable();
            $t->text('catatan')->nullable();

            /* Sertifikat yang lahir dari pelatihan di dalam aplikasi
               ini sendiri menunjuk balik ke sertifikat LMS-nya, supaya
               yang diterbitkan sistem tidak tercatat dua kali sebagai
               dua dokumen yang berbeda. */
            $t->foreignId('certificate_id')->nullable()
                ->constrained('certificates')->nullOnDelete();

            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();

            $t->index(['company_id', 'berlaku_sampai']);
            $t->index(['pekerja_id']);
        });
    }
};
