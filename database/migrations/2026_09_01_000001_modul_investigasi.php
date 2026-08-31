<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Investigasi kecelakaan — dari laporan insiden sampai pembelajaran.
 *
 * MENGAPA MODUL TERSENDIRI, BUKAN BAGIAN HAZARD REPORT.
 *
 * Hazard Report menjawab "ada bahaya, tolong ditangani": pelapor
 * menyebutkan apa yang dilihatnya, seseorang menutupnya, selesai.
 * Investigasi kecelakaan menjawab pertanyaan yang sama sekali lain —
 * "mengapa ini terjadi, dan apa yang membuatnya tidak terulang" — dan
 * jawabannya berupa berkas yang dapat diminta Inspektur Tambang.
 * Memaksakan keduanya ke satu tabel berarti tiap kolom investigasi
 * menjadi kolom yang selalu kosong pada laporan bahaya biasa, dan tiap
 * penjagaan investigasi harus memeriksa lebih dulu "ini sebenarnya
 * laporan jenis apa".
 *
 * MENGAPA INSIDEN DAN INVESTIGASI DIPISAH.
 *
 * Tidak setiap insiden berujung investigasi, dan itu benar: pekerja
 * yang tergores ranting dicatat, ditriase, lalu ditutup tanpa tim
 * investigasi. Menyatukan keduanya berarti tiap insiden ringan
 * melahirkan berkas investigasi kosong — dan berkas kosong yang
 * jumlahnya ribuan membuat berkas yang sungguh berisi tidak lagi
 * dapat ditemukan.
 *
 * Nomornya pun terpisah: INC-2026-0011 dan INV-2026-0009 pada
 * kejadian yang sama. Keduanya dirujuk di dokumen berbeda oleh orang
 * berbeda, dan satu nomor untuk dua benda membuat "sudah sampai mana
 * INC-2026-0011" menjadi pertanyaan yang punya dua jawaban.
 *
 * ACUAN REGULASI — jangan diubah tanpa memeriksa teks resminya:
 *   · Kepmen ESDM 1827 K/30/MEM/2018 Lampiran III — batas hari cidera
 *     ringan (>1 hari, <3 minggu) dan berat (≥3 minggu atau cacat tetap).
 *   · Kepdirjen Minerba 185/2019 — lima kriteria kecelakaan tambang,
 *     kewajiban penyelidikan KTT/PTL, dan tabel hari hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ═══════════════════ MASTER ═══════════════════
         *
         * Seluruh master di bawah ini TIDAK berkolom company_id, dan
         * itu disengaja. Isinya kerangka resmi — matriks risiko,
         * klasifikasi menurut Kepmen, hierarki pengendalian, kamus
         * penyebab SCAT/ICAM — yang berlaku sama bagi setiap perusahaan
         * di negara yang sama. Menyalinnya per perusahaan berarti
         * revisi regulasi harus dijalankan sebanyak jumlah perusahaan,
         * dan yang tertinggal akan menghitung level investigasi dengan
         * matriks lama tanpa seorang pun tahu.
         *
         * Yang BERKOLOM company_id hanya inv_lokasi: nama pit, ramp,
         * dan workshop memang milik masing-masing tambang.
         */

        Schema::create('inv_lokasi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kode', 30);
            $t->string('nama', 120);
            $t->string('area', 120)->nullable();
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            /* Indeks DIAWALI company_id, bukan sekadar memuatnya.
               MilikPerusahaan menambahkan `where company_id = ?` pada
               setiap kueri ke tabel ini — indeks yang company_id-nya di
               kolom kedua tidak menolong sama sekali, dan dari daftar
               indeks ia terlihat seolah menolong. */
            $t->index(['company_id', 'aktif']);
        });

        Schema::create('inv_jenis_insiden', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 30)->unique();
            $t->string('nama', 120);
            $t->string('kelompok', 30)->index();   // injury/property/process/environment/security
            $t->integer('urutan')->default(0);
            $t->timestamps();
        });

        /**
         * Klasifikasi cedera.
         *
         * Menggabungkan istilah regulasi Indonesia (ringan/berat/mati)
         * dengan istilah statistik industri (near miss, FAI, MTI, RWC,
         * LTI). Keduanya dipakai bersamaan di lapangan: yang pertama
         * untuk laporan ke Inspektur Tambang, yang kedua untuk
         * menghitung FR/SR dan membandingkannya dengan kontraktor lain.
         */
        Schema::create('inv_klasifikasi_cedera', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 30)->unique();
            $t->string('nama', 120);
            $t->integer('hari_min')->nullable();
            $t->integer('hari_maks')->nullable();

            /* Kematian dan cacat tetap total dihitung SETARA 6.000 hari
               menurut tabel Kepdirjen 185/2019 — bukan dihitung dari
               hari absen sebenarnya, yang bagi korban meninggal tidak
               pernah berhenti bertambah. */
            $t->integer('hari_hilang_standar')->nullable();
            $t->text('keterangan')->nullable();
            $t->integer('urutan')->default(0);
            $t->timestamps();
        });

        Schema::create('inv_klasifikasi_regulasi', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 40)->unique();
            $t->string('nama', 120);
            $t->boolean('wajib_lapor_kait')->default(false);
            $t->text('keterangan')->nullable();
            $t->integer('urutan')->default(0);
            $t->timestamps();
        });

        /**
         * Matriks risiko 5×5.
         *
         * Disimpan sebagai BARIS, bukan sebagai rumus di dalam kode.
         * Bedanya menentukan ketika seseorang bertanya "mengapa
         * kejadian ini L3": jawabannya dapat ditunjuk pada satu baris
         * yang dapat dibaca dan diaudit, bukan pada percabangan if yang
         * hanya dapat dibaca pemrogram.
         */
        Schema::create('inv_matriks_risiko', function (Blueprint $t) {
            $t->id();
            $t->integer('kemungkinan');
            $t->integer('keparahan');
            $t->integer('skor');
            $t->string('pita', 20);              // rendah/sedang/tinggi/kritis
            $t->string('level_investigasi', 5);  // L1..L4
            $t->timestamps();

            $t->unique(['kemungkinan', 'keparahan']);
        });

        Schema::create('inv_hierarki_kendali', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 30)->unique();
            $t->string('nama', 120);
            $t->integer('tingkat');              // 1 eliminasi … 5 APD
            $t->text('keterangan')->nullable();
            $t->timestamps();
        });

        /**
         * Kamus penyebab — SCAT, ICAM, Tripod, HFACS.
         *
         * TapRooT sengaja TIDAK disertakan: metode itu berlisensi, dan
         * menyalin kamus penyebabnya ke dalam basis data adalah
         * pelanggaran lisensi.
         */
        Schema::create('inv_taksonomi', function (Blueprint $t) {
            $t->id();
            $t->string('metode', 20)->index();   // scat/icam/tripod/hfacs
            $t->string('kategori', 60)->nullable();
            $t->string('kode', 30);
            $t->string('label', 200);
            $t->integer('tingkat')->nullable();
            $t->text('definisi')->nullable();
            $t->integer('urutan')->default(0);
            $t->timestamps();

            /* Kode unik PER METODE, bukan secara global. SCAT dan ICAM
               sama-sama punya butir berkode "1"; unik global akan
               membuat yang kedua gagal tersimpan diam-diam. */
            $t->unique(['metode', 'kode']);
        });

        /**
         * Nomor berjalan per awalan dan per tahun.
         *
         * Tabel tersendiri, bukan MAX(no_insiden)+1. Menghitung dari
         * baris yang ada berarti nomor yang barisnya terhapus akan
         * dipakai ulang — dan dua dokumen bernomor sama pada tahun yang
         * sama adalah persoalan yang baru ketahuan saat salah satunya
         * dicari.
         */
        Schema::create('inv_nomor_urut', function (Blueprint $t) {
            $t->id();
            $t->string('prefix', 10);
            $t->integer('tahun');
            $t->integer('nomor_terakhir')->default(0);
            $t->timestamps();

            $t->unique(['prefix', 'tahun']);
        });

        /* ═══════════════════ INSIDEN ═══════════════════ */

        Schema::create('inv_insiden', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('no_insiden', 30)->unique();

            $t->string('judul', 200);
            $t->date('tanggal_kejadian');
            $t->time('waktu_kejadian')->nullable();
            $t->dateTime('dilaporkan_pada')->nullable();

            $t->foreignId('lokasi_id')->nullable()->constrained('inv_lokasi')->nullOnDelete();
            $t->string('lokasi_rinci', 200)->nullable();
            $t->string('aktivitas', 200)->nullable();

            $t->foreignId('jenis_insiden_id')->nullable()->constrained('inv_jenis_insiden')->nullOnDelete();
            $t->foreignId('klasifikasi_cedera_id')->nullable()->constrained('inv_klasifikasi_cedera')->nullOnDelete();
            $t->foreignId('klasifikasi_regulasi_id')->nullable()->constrained('inv_klasifikasi_regulasi')->nullOnDelete();
            $t->foreignId('pelapor_id')->nullable()->constrained('users')->nullOnDelete();

            /* ── TRIASE ──
             *
             * `keparahan_potensial` berdiri sendiri di samping
             * `keparahan`, dan itu kolom yang paling mudah dikira
             * mubazir. Sebuah unit yang lepas kendali lalu berhenti
             * satu meter dari pekerja tidak melukai siapa pun —
             * keparahan nyatanya 1. Potensinya fatal. Menyimpan yang
             * nyata saja menurunkan kejadian semacam itu ke L1 dan
             * membuang justru pelajaran yang paling mahal. */
            $t->integer('kemungkinan')->nullable();
            $t->integer('keparahan')->nullable();
            $t->integer('keparahan_potensial')->nullable();
            $t->integer('skor_risiko')->nullable();
            $t->string('pita_risiko', 20)->nullable();
            $t->string('level_investigasi', 5)->nullable()->index();

            /* Lima kriteria kecelakaan tambang, Kepdirjen 185/2019.
               Disimpan satu per satu dan bukan sebagai satu boolean
               kesimpulan: yang ditanya Inspektur Tambang adalah
               kriteria mana yang tidak terpenuhi, dan kesimpulan tunggal
               tidak dapat menjawabnya. */
            $t->boolean('k1_benar_terjadi')->default(false);
            $t->boolean('k2_mencederai_pekerja')->default(false);
            $t->boolean('k3_akibat_kegiatan')->default(false);
            $t->boolean('k4_jam_kerja')->default(false);
            $t->boolean('k5_wilayah_usaha')->default(false);

            $t->boolean('wajib_lapor_kait')->default(false)->index();

            /* Tenggat dihitung dari WAKTU KEJADIAN, bukan waktu
               pelaporan. Dihitung dari pelaporan, keterlambatan melapor
               akan memperpanjang tenggatnya sendiri — dan tidak ada
               satu pun laporan yang akan pernah terlambat. */
            $t->dateTime('tenggat_lapor')->nullable();
            $t->dateTime('tenggat_selidik')->nullable();
            $t->dateTime('dilaporkan_kait_pada')->nullable();

            $t->text('kronologi')->nullable();
            $t->text('tindakan_segera')->nullable();

            /* ── PEMICU SARAN LAPIS 3 ──
             *
             * Enam kolom ini yang membuat lapis ketiga mesin saran SCAT
             * menyala. Tanpa medan yang menanyakannya di formulir,
             * seluruh kode lapis 3 tetap benar dan tetap tidak pernah
             * berjalan — cacat yang paling mudah lolos, sebab ujinya
             * hijau memakai data contoh yang mengisi kolomnya langsung. */
            $t->boolean('p_shift_malam')->default(false);
            $t->boolean('p_lembur_panjang')->default(false);
            $t->boolean('p_sop_tidak_ada')->default(false);
            $t->boolean('p_belum_dilatih')->default(false);
            $t->boolean('p_inspeksi_absen')->default(false);
            $t->boolean('p_insiden_berulang')->default(false);

            $t->string('status', 30)->default('dilaporkan')->index();
            $t->timestamps();
            $t->softDeletes();

            /* Diawali company_id, dengan tanggal kejadian sesudahnya:
               hampir setiap layar modul ini membaca "insiden perusahaan
               ini, terbaru dahulu", dan kedua kolom itu yang dipakainya. */
            $t->index(['company_id', 'tanggal_kejadian']);
        });

        Schema::create('inv_insiden_orang', function (Blueprint $t) {
            $t->id();
            $t->foreignId('insiden_id')->constrained('inv_insiden')->cascadeOnDelete();
            $t->string('nama', 120);
            $t->string('jabatan', 120)->nullable();
            $t->string('perusahaan', 120)->nullable();
            $t->string('peran', 20)->default('korban');   // korban/saksi/terlibat
            $t->string('bagian_tubuh', 120)->nullable();
            $t->text('rincian_cedera')->nullable();
            $t->integer('hari_hilang')->nullable();
            $t->timestamps();
        });

        /* ═══════════════════ INVESTIGASI ═══════════════════ */

        Schema::create('inv_investigasi', function (Blueprint $t) {
            $t->id();
            $t->string('no_investigasi', 30)->unique();
            $t->foreignId('insiden_id')->constrained('inv_insiden')->cascadeOnDelete();
            $t->foreignId('ketua_id')->nullable()->constrained('users')->nullOnDelete();

            $t->string('prioritas', 20)->default('sedang');
            $t->date('target_selesai')->nullable();
            $t->string('tahap', 30)->default('perencanaan')->index();
            $t->string('status', 30)->default('berjalan')->index();

            $t->text('tujuan')->nullable();
            $t->text('ruang_lingkup')->nullable();
            $t->string('metode', 100)->nullable();
            $t->dateTime('ditutup_pada')->nullable();
            $t->timestamps();
        });

        Schema::create('inv_tim', function (Blueprint $t) {
            $t->id();
            $t->foreignId('investigasi_id')->constrained('inv_investigasi')->cascadeOnDelete();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->string('peran_tim', 60)->nullable();
            $t->timestamps();

            $t->unique(['investigasi_id', 'user_id']);
        });

        /**
         * Bukti — dan sidik jarinya.
         *
         * `sha256` dihitung saat berkasnya masuk lalu tidak pernah
         * dihitung ulang. Gunanya bukan mencegah penggantian — siapa
         * pun yang dapat mengunggah dapat mengunggah yang lain — tetapi
         * membuat penggantian TERLIHAT: berkas yang isinya berubah
         * punya sidik jari yang berbeda dari yang tercatat, dan
         * selisihnya adalah pertanyaan yang harus dijawab seseorang.
         *
         * `dikunci` menutup baris dari perubahan. Bukti yang sudah
         * dikunci tidak dapat dihapus maupun diganti berkasnya; yang
         * dapat dilakukan hanya menambah bukti baru. Pada berkas yang
         * dapat diminta Inspektur Tambang, kemampuan menghapus bukti
         * setelah kesimpulan ditulis adalah lubang yang tidak dapat
         * dijelaskan kepada siapa pun.
         */
        Schema::create('inv_bukti', function (Blueprint $t) {
            $t->id();
            $t->string('no_bukti', 30)->unique();
            $t->foreignId('investigasi_id')->constrained('inv_investigasi')->cascadeOnDelete();

            $t->string('jenis', 30);          // foto/video/dokumen/cctv/pernyataan/fisik
            $t->string('judul', 200);
            $t->text('keterangan')->nullable();
            $t->string('sumber', 200)->nullable();
            $t->date('dikumpulkan_pada')->nullable();

            $t->string('berkas', 255)->nullable();
            $t->string('mime', 100)->nullable();
            $t->string('sha256', 64)->nullable();

            $t->boolean('dikunci')->default(false);
            $t->dateTime('dikunci_pada')->nullable();
            $t->foreignId('dikunci_oleh')->nullable()->constrained('users')->nullOnDelete();

            $t->foreignId('dikumpulkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('inv_kronologi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('investigasi_id')->constrained('inv_investigasi')->cascadeOnDelete();
            $t->dateTime('waktu')->nullable();
            $t->string('peristiwa', 300);
            $t->text('keterangan')->nullable();

            /* Menandai peristiwa yang diduga menjadi penyebab. Dipakai
               mesin saran SCAT sebagai lapis pertama — kata pada
               peristiwa bertanda inilah yang dicocokkan ke kamus. */
            $t->boolean('penyebab')->default(false);
            $t->integer('urutan')->default(0);
            $t->timestamps();
        });

        /* ═══════════════════ ANALISIS ═══════════════════ */

        Schema::create('inv_analisis', function (Blueprint $t) {
            $t->id();
            $t->foreignId('investigasi_id')->constrained('inv_investigasi')->cascadeOnDelete();
            $t->string('metode', 20);         // scat/icam/tripod/hfacs
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['investigasi_id', 'metode']);
        });

        /**
         * Butir penyebab yang dipilih investigator.
         *
         * Tersimpan sebagai KUNCI ASING ke inv_taksonomi, bukan sebagai
         * teks. Disimpan sebagai teks, rekap "penyebab terbanyak tahun
         * ini" akan memecah satu penyebab menjadi lima karena ejaannya
         * berbeda-beda — dan rekap itulah satu-satunya alasan kamus
         * penyebab ada.
         *
         * `dari_saran` dan `lapis_saran` mencatat apakah butir ini
         * muncul karena disarankan mesin, dan dari lapis mana. Itu yang
         * memungkinkan pertanyaan "apakah mesinnya menolong atau justru
         * menyetir" dijawab dengan angka di kemudian hari.
         */
        Schema::create('inv_scat_pilihan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('analisis_id')->constrained('inv_analisis')->cascadeOnDelete();
            $t->foreignId('taksonomi_id')->constrained('inv_taksonomi')->cascadeOnDelete();
            $t->boolean('dari_saran')->default(false);
            $t->integer('lapis_saran')->nullable();
            $t->text('catatan_lapangan')->nullable();
            $t->timestamps();

            $t->unique(['analisis_id', 'taksonomi_id']);
        });

        /**
         * Akar masalah — dan buktinya.
         *
         * `taksonomi_id` boleh kosong: sebagian akar masalah lahir dari
         * 5 Why dan tidak punya padanan di kamus mana pun. Yang tidak
         * boleh adalah akar masalah tanpa bukti — itu pendapat, dan
         * pendapat tidak bertahan di depan Inspektur Tambang. Tautannya
         * ada di inv_akar_bukti.
         */
        Schema::create('inv_akar_masalah', function (Blueprint $t) {
            $t->id();
            $t->foreignId('investigasi_id')->constrained('inv_investigasi')->cascadeOnDelete();
            $t->text('uraian');
            $t->string('metode', 20)->nullable();
            $t->foreignId('taksonomi_id')->nullable()->constrained('inv_taksonomi')->nullOnDelete();
            $t->integer('urutan')->default(0);
            $t->timestamps();
        });

        Schema::create('inv_akar_bukti', function (Blueprint $t) {
            $t->id();
            $t->foreignId('akar_id')->constrained('inv_akar_masalah')->cascadeOnDelete();
            $t->foreignId('bukti_id')->constrained('inv_bukti')->cascadeOnDelete();
            $t->timestamps();

            $t->unique(['akar_id', 'bukti_id']);
        });

        Schema::create('inv_temuan', function (Blueprint $t) {
            $t->id();
            $t->string('no_temuan', 30)->unique();
            $t->foreignId('investigasi_id')->constrained('inv_investigasi')->cascadeOnDelete();
            $t->foreignId('akar_id')->nullable()->constrained('inv_akar_masalah')->nullOnDelete();
            $t->text('uraian');
            $t->text('rekomendasi')->nullable();
            $t->string('tingkat', 20)->default('sedang');   // rendah/sedang/tinggi
            $t->integer('urutan')->default(0);
            $t->timestamps();
        });

        /**
         * Tindakan perbaikan (CAPA).
         *
         * `hierarki_id` menyebut tingkat pengendaliannya. Itu bukan
         * hiasan: investigasi yang seluruh tindakannya berupa "briefing
         * ulang" dan "pasang rambu" adalah investigasi yang tidak
         * mengubah apa pun, dan satu-satunya cara melihatnya dari jauh
         * adalah dengan mencatat tingkat tiap tindakan.
         */
        Schema::create('inv_tindakan', function (Blueprint $t) {
            $t->id();
            $t->string('no_tindakan', 30)->unique();
            $t->foreignId('temuan_id')->constrained('inv_temuan')->cascadeOnDelete();
            $t->foreignId('hierarki_id')->nullable()->constrained('inv_hierarki_kendali')->nullOnDelete();

            $t->text('uraian');
            $t->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('pic_nama', 120)->nullable();
            $t->date('tenggat')->nullable();

            $t->string('status', 20)->default('terbuka');  // terbuka/berjalan/selesai/diverifikasi/ditutup
            $t->date('selesai_pada')->nullable();
            $t->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->date('diverifikasi_pada')->nullable();
            $t->text('catatan_verifikasi')->nullable();
            $t->boolean('efektif')->nullable();
            $t->timestamps();
        });

        Schema::create('inv_pembelajaran', function (Blueprint $t) {
            $t->id();
            $t->foreignId('investigasi_id')->constrained('inv_investigasi')->cascadeOnDelete();
            $t->string('judul', 200);
            $t->text('ringkasan');
            $t->text('pesan_kunci')->nullable();
            $t->date('diterbitkan_pada')->nullable();
            $t->foreignId('diterbitkan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        /* ═══════════════════ WAWANCARA ═══════════════════ */

        Schema::create('inv_wawancara_pertanyaan', function (Blueprint $t) {
            $t->id();
            $t->string('kode', 30)->unique();
            $t->text('pertanyaan');

            /* Peran narasumber yang cocok, dan tingkat hierarki kendali
               yang boleh ditanyakan kepadanya. Korban ditanya soal APD;
               menanyainya mengapa perusahaan tidak mengganti alatnya
               bukan hanya sia-sia, ia menggeser tanggung jawab
               organisasi ke orang yang baru saja celaka. */
            $t->string('peran', 30)->index();
            $t->integer('tingkat_hierarki')->nullable();
            $t->string('kata_kunci', 200)->nullable();
            $t->integer('urutan')->default(0);
            $t->timestamps();
        });

        Schema::create('inv_wawancara', function (Blueprint $t) {
            $t->id();
            $t->foreignId('investigasi_id')->constrained('inv_investigasi')->cascadeOnDelete();
            $t->string('narasumber', 120);
            $t->string('jabatan', 120)->nullable();
            $t->string('peran', 30);          // korban/saksi_langsung/saksi_tidak_langsung/pengawas/manajemen
            $t->date('tanggal')->nullable();
            $t->string('tempat', 120)->nullable();
            $t->text('catatan')->nullable();
            $t->foreignId('pewawancara_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        Schema::create('inv_wawancara_jawaban', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wawancara_id')->constrained('inv_wawancara')->cascadeOnDelete();
            $t->foreignId('pertanyaan_id')->nullable()->constrained('inv_wawancara_pertanyaan')->nullOnDelete();

            /* Bunyi pertanyaannya IKUT DISALIN, bukan hanya dirujuk.
               Bank soal boleh diperbaiki kapan saja; berita acara
               wawancara yang sudah ditandatangani tidak boleh ikut
               berubah bunyinya karena seseorang memperbaiki ejaan di
               master setahun kemudian. */
            $t->text('pertanyaan_teks');
            $t->text('jawaban');
            $t->timestamps();
        });

        /* ═══════════════════ JEJAK ═══════════════════ */

        Schema::create('inv_jejak', function (Blueprint $t) {
            $t->id();
            $t->foreignId('investigasi_id')->nullable()->constrained('inv_investigasi')->cascadeOnDelete();
            $t->foreignId('insiden_id')->nullable()->constrained('inv_insiden')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('aksi', 60);
            $t->text('keterangan')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        /* Urutannya terbalik dari pembuatannya — anak lebih dahulu.
           Tanpa itu, penghapusan tabel induk gagal karena kunci asing
           yang masih menunjuk kepadanya, dan migrasi yang gagal di
           tengah meninggalkan basis data separuh jadi. */
        foreach ([
            'inv_jejak',
            'inv_wawancara_jawaban', 'inv_wawancara', 'inv_wawancara_pertanyaan',
            'inv_pembelajaran', 'inv_tindakan', 'inv_temuan',
            'inv_akar_bukti', 'inv_akar_masalah',
            'inv_scat_pilihan', 'inv_analisis',
            'inv_kronologi', 'inv_bukti', 'inv_tim', 'inv_investigasi',
            'inv_insiden_orang', 'inv_insiden',
            'inv_nomor_urut',
            'inv_taksonomi', 'inv_hierarki_kendali', 'inv_matriks_risiko',
            'inv_klasifikasi_regulasi', 'inv_klasifikasi_cedera',
            'inv_jenis_insiden', 'inv_lokasi',
        ] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }
};
