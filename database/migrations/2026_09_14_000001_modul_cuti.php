<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuti & izin — dan sambungannya ke roster.
 *
 * YANG MEMBUAT MODUL INI BUKAN FORMULIR: cuti yang disetujui MENULIS
 * KE ROSTER. Tanpa itu, seorang yang cutinya disetujui tetap tercatat
 * dijadwalkan kerja pada kalender regunya — lalu tercatat mangkir pada
 * layar absensi, dan pengawas pos jaga menelusuri ketidakhadiran yang
 * sudah disetujui atasannya sendiri seminggu sebelumnya.
 *
 * ACUAN, DAN ANGKANYA BUKAN KARANGAN:
 *
 *   · UU 13/2003 pasal 79 — cuti tahunan 12 hari kerja, timbul
 *     sesudah bekerja 12 bulan terus-menerus.
 *   · UU 13/2003 pasal 81 — cuti haid hari pertama dan kedua.
 *   · UU 13/2003 pasal 82 — istirahat melahirkan 1,5 bulan sebelum
 *     dan 1,5 bulan sesudah; keguguran 1,5 bulan.
 *   · UU 13/2003 pasal 93 ayat (4) — izin khusus berbayar: menikah 3
 *     hari, menikahkan anak 2 hari, khitan/baptis anak 2 hari, istri
 *     melahirkan/keguguran 2 hari, keluarga inti meninggal 2 hari,
 *     anggota keluarga serumah meninggal 1 hari.
 *
 * Lamanya disimpan sebagai DATA, bukan ditulis di dalam kode
 * perhitungannya. Putusan MK 168/PUU-XXI/2023 memerintahkan undang-
 * undang ketenagakerjaan baru paling lambat 31 Oktober 2026 — angka
 * yang tertanam di dalam kode berarti satu penerapan ulang untuk tiap
 * pasal yang berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * Jenis cuti — daftar awal bersama, tanpa pemilik.
         *
         * Isinya pasal undang-undang yang berlaku sama bagi setiap
         * perusahaan, jadi ia dipasang sekali dan dipakai semuanya.
         * Perusahaan yang PKB-nya lebih longgar menyunting lamanya;
         * yang disunting adalah barisnya, bukan salinannya.
         */
        Schema::create('hr_jenis_cuti', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            /* Identitas yang tetap, dipakai pemasangan ulang mengenali
               barisnya. Dikenali lewat nama, satu penggantian nama
               melahirkan kembar pada tiap penerapan — dan pengajuan
               cuti yang sudah ada menunjuk ke yang lama. */
            $t->string('kunci', 40);

            $t->string('kode', 20);
            $t->string('nama', 120);

            /* Pasal yang mendasarinya, ditulis apa adanya. Yang
               menolak pengajuan harus dapat menunjukkan dasarnya, dan
               "sistem menolak" bukan dasar. */
            $t->string('dasar', 120)->nullable();

            /* Lama bawaan sekali kejadian. NULL berarti lamanya tidak
               dipatok undang-undang — cuti sakit sepanjang surat
               dokternya, cuti tanpa upah sepanjang yang disetujui. */
            $t->unsignedSmallInteger('hari')->nullable();

            /* Memotong saldo tahunan atau tidak. Cuti sakit dan izin
               khusus TIDAK memotongnya: pasal 93 menyebutnya upah tetap
               dibayar, bukan cuti tahunan yang dipakai. Dipotong,
               seorang yang ayahnya meninggal kehilangan dua hari cuti
               tahunannya. */
            $t->boolean('potong_saldo')->default(false);

            $t->boolean('berbayar')->default(true);

            /* Menuntut bukti unggahan — surat dokter, surat nikah,
               surat keterangan kematian. */
            $t->boolean('perlu_bukti')->default(false);

            /* Saldonya berakru tiap tahun. Hanya cuti tahunan; izin
               khusus timbul dari kejadiannya, bukan dari saldo. */
            $t->boolean('akrual')->default(false);

            /* Sisa yang boleh dibawa ke tahun berikutnya. */
            $t->unsignedSmallInteger('carry_over_maks')->default(0);

            /* Keadaan yang dituliskan ke baris roster saat disetujui —
               salah satu dari Roster::KEADAAN. Di situlah cuti bertemu
               jadwal. */
            $t->string('keadaan_roster', 10)->default('cuti');

            $t->unsignedSmallInteger('urutan')->default(0);
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
            $t->index(['company_id', 'kunci']);
        });

        /**
         * Saldo per orang, per jenis, per tahun.
         *
         * DISIMPAN, TIDAK DIHITUNG SAAT DIBACA. Hak cuti seseorang
         * bergantung pada masa kerjanya dan pada kebijakan yang berlaku
         * saat itu; dihitung ulang tiap kali layarnya dibuka, saldo
         * tahun lalu ikut berubah begitu kebijakannya diubah — dan
         * sengketa upah bertumpu pada angka yang tidak dapat
         * ditunjukkan lagi asalnya.
         */
        Schema::create('hr_saldo_cuti', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();
            $t->foreignId('jenis_cuti_id')->constrained('hr_jenis_cuti')->cascadeOnDelete();

            $t->unsignedSmallInteger('tahun');

            /* Hak tahun berjalan, sisa bawaan tahun lalu, dan yang
               sudah dipakai. Ketiganya terpisah supaya slip cuti dapat
               menjelaskan angkanya, bukan sekadar menyebutkan sisanya. */
            $t->unsignedSmallInteger('hak')->default(0);
            $t->unsignedSmallInteger('carry_over')->default(0);
            $t->unsignedSmallInteger('terpakai')->default(0);

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['pekerja_id', 'jenis_cuti_id', 'tahun']);
            $t->index(['company_id', 'tahun']);
        });

        /**
         * Pengajuan cuti.
         *
         * SALDO DIPOTONG SAAT DISETUJUI, BUKAN SAAT DIAJUKAN — tetapi
         * pengajuan yang masih menunggu tetap DIPERHITUNGKAN saat
         * memeriksa kecukupan saldo. Dipotong saat diajukan, pengajuan
         * yang ditolak memakan saldo selamanya; tidak diperhitungkan
         * sama sekali, dua pengajuan yang menunggu dapat disetujui
         * berdua dan saldonya menjadi minus tanpa satu galat pun.
         */
        Schema::create('hr_cuti', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();
            $t->foreignId('jenis_cuti_id')->constrained('hr_jenis_cuti')->restrictOnDelete();

            $t->date('mulai');
            $t->date('selesai');

            /* DUA ANGKA, dan pemisahannya yang menentukan. `kalender`
               adalah panjang rentangnya; `hari` adalah hari KERJA di
               dalamnya menurut roster orang itu.

               Pada pola 14:7 keduanya berbeda jauh: cuti tiga hari yang
               jatuh pada periode off-site tidak memakan satu pun hari
               kerja. Dihitung dari kalender, seorang pekerja FIFO
               kehilangan seluruh dua belas hari cuti tahunannya dalam
               satu periode libur yang memang haknya. */
            $t->unsignedSmallInteger('hari')->default(0);
            $t->unsignedSmallInteger('kalender')->default(0);

            $t->text('alasan')->nullable();
            $t->string('berkas_bukti')->nullable();

            /* menunggu | disetujui | ditolak | dibatalkan */
            $t->string('status', 12)->default('menunggu');

            /* Diteruskan ke jenjang di atasnya. Mock PRD menyebutnya
               "Teruskan ke Superintendent": atasan langsung boleh
               menyetujui yang biasa, tetapi yang berdampak pada
               manpower site diteruskan. */
            $t->boolean('perlu_jenjang')->default(false);

            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();

            $t->foreignId('ditindak_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditindak_pada')->nullable();
            $t->text('catatan_tindak')->nullable();

            $t->timestamps();

            $t->index(['company_id', 'mulai']);
            $t->index(['pekerja_id', 'mulai']);
            $t->index(['company_id', 'status']);
        });

        /**
         * Baris roster yang lahir dari sebuah cuti.
         *
         * DITANDAI, bukan sekadar diubah keadaannya. Tiga hal
         * bergantung padanya:
         *
         *   · penyusunan ulang baseline harus MELEWATI baris ini —
         *     tanpa tanda, menekan "susun ulang" karena satu orang
         *     pindah regu menghapus seluruh cuti yang sudah disetujui
         *     bulan itu, dan tidak ada satu galat pun yang menandainya;
         *
         *   · pembatalan cuti harus mengembalikan persis baris yang
         *     diubahnya, bukan setiap baris yang kebetulan berkeadaan
         *     sama;
         *
         *   · yang membaca kalender berhak tahu MENGAPA sebuah hari
         *     berubah menjadi cuti, dan menelusurinya ke pengajuannya.
         */
        Schema::table('hr_roster', function (Blueprint $t) {
            $t->foreignId('cuti_id')->nullable()->after('user_id')
                ->constrained('hr_cuti')->nullOnDelete();
        });

        /**
         * Tanggal masuk kerja.
         *
         * Hak cuti tahunan baru timbul sesudah dua belas bulan bekerja
         * terus-menerus (UU 13/2003 pasal 79). Tanpa tanggal ini, hak
         * itu tidak dapat dihitung sama sekali — dan memberikannya
         * kepada semua orang sejak hari pertama adalah kesalahan yang
         * mahal dan tidak terlihat sampai seseorang resign dengan sisa
         * cuti yang harus diuangkan.
         */
        Schema::table('mnr_pekerja', function (Blueprint $t) {
            $t->date('tanggal_masuk')->nullable()->after('tanggal_lahir');
        });
    }

    public function down(): void
    {
        Schema::table('mnr_pekerja', fn (Blueprint $t) => $t->dropColumn('tanggal_masuk'));

        Schema::table('hr_roster', function (Blueprint $t) {
            $t->dropForeign(['cuti_id']);
            $t->dropColumn('cuti_id');
        });

        Schema::dropIfExists('hr_cuti');
        Schema::dropIfExists('hr_saldo_cuti');
        Schema::dropIfExists('hr_jenis_cuti');
    }
};
