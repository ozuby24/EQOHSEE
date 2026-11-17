<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roster & shift — pola kerja bergilir di tambang terpencil.
 *
 * Menjawab pertanyaan yang tidak dapat dijawab HRIS umum: siapa yang
 * seharusnya berada di site pada tanggal berapa, dan bolehkah ia
 * berada di sana. Pola 14:7 — empat belas hari kerja, tujuh hari
 * libur — bukan jadwal mingguan yang digeser; ia siklus yang berjalan
 * terus melintasi batas bulan, dan spreadsheet yang menyusunnya
 * manual adalah sebab paling sering jadwal mobilisasi meleset.
 *
 * DUA LAPIS, DAN ITU YANG MEMBUATNYA DAPAT DIPAKAI:
 *
 *   Pola + regu menghitung baseline-nya. Satu regu menempel pada satu
 *   pola beserta TANGGAL JANGKARnya, sehingga keadaan hari mana pun
 *   dapat dihitung tanpa menyimpan satu baris pun.
 *
 *   `hr_roster` menyimpan hasil yang SUDAH DITERBITKAN, beserta
 *   suntingannya — tukar jaga, cuti, sakit. Tanpa lapis kedua,
 *   seorang yang bertukar jaga akan kembali ke pola aslinya begitu
 *   layarnya dimuat ulang.
 *
 * ACUAN — jangan diubah tanpa memeriksa teks resminya:
 *   · Kepmenakertrans KEP.234/MEN/2003 — waktu kerja sektor ESDM pada
 *     daerah tertentu: maks 11 jam/hari, 154 jam per 14 hari, paling
 *     lama 14 hari kerja berturut-turut, istirahat sekurangnya 5 hari.
 *   · UU 13/2003 jo. UU 6/2023 — batas umum 40 jam seminggu.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * Pola roster — 14:7, 10:2 minggu, 4:1, dan seterusnya.
         *
         * SATUANNYA DISIMPAN, bukan disimpulkan dari angkanya. "14:7"
         * berarti hari, "10:2" pada beberapa entitas berarti MINGGU —
         * sepuluh minggu di site, dua minggu pulang. Disimpulkan dari
         * besar angkanya, pola 10:2 minggu akan dihitung sebagai 10
         * hari dan seorang pekerja dipulangkan tujuh puluh hari terlalu
         * cepat.
         */
        Schema::create('hr_pola_roster', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kunci', 60)->nullable()->index();
            $t->string('kode', 20);
            $t->string('nama', 120);

            $t->unsignedSmallInteger('kerja');
            $t->unsignedSmallInteger('libur');

            /* hari | minggu */
            $t->string('satuan', 10)->default('hari');

            /* Jam kerja sehari. Kepmenakertrans 234/2003 membatasinya
               11 jam bagi sektor ESDM di daerah tertentu; disimpan per
               pola supaya pola kantor pusat yang 8 jam tidak ikut
               diperiksa terhadap batas lapangan. */
            $t->unsignedTinyInteger('jam')->default(11);

            /**
             * Hari libur DI DALAM periode kerja, per tujuh hari.
             *
             * Pola bersatuan minggu bukan berarti bekerja tanpa jeda
             * selama sepuluh minggu. "10:2 minggu" berarti sepuluh
             * minggu di site lalu dua minggu pulang — dan di dalam
             * sepuluh minggu itu tetap ada hari libur mingguan.
             *
             * Dimodelkan sebagai tujuh puluh hari kerja berturut-turut,
             * polanya melanggar batas empat belas hari Kepmenakertrans
             * 234/2003 pada tiap siklusnya — dan aplikasi menandai
             * merah pola yang justru dipakai sungguhan di lapangan.
             * Nol berarti memang tanpa jeda, seperti pola 14:7.
             */
            $t->unsignedTinyInteger('libur_mingguan')->default(0);

            /* siang | malam | putar — `putar` berarti bergantian antar
               siklus, dan itulah yang dipakai kebanyakan regu. */
            $t->string('shift', 10)->default('siang');

            $t->text('keterangan')->nullable();
            $t->unsignedSmallInteger('urutan')->default(100);
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
        });

        /**
         * Regu — sekumpulan orang yang bergerak bersama pada satu pola.
         *
         * TANGGAL JANGKARNYA yang membuat pola dapat dihitung. Dua regu
         * pada pola 14:7 yang sama tetapi berjangkar tujuh hari
         * berselisih akan saling mengisi: yang satu pulang ketika yang
         * lain datang. Tanpa jangkar, seluruh regu libur pada minggu
         * yang sama dan site kosong.
         */
        Schema::create('hr_regu', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('pola_roster_id')->nullable()->constrained('hr_pola_roster')->nullOnDelete();
            $t->foreignId('blok_id')->nullable()->constrained('mnr_blok')->nullOnDelete();

            $t->string('nama', 80);
            $t->date('mulai');

            /* Shift regu ini bila polanya `putar` — disimpan supaya dua
               regu pada pola yang sama dapat berlawanan siangnya. */
            $t->string('shift', 10)->nullable();

            $t->text('catatan')->nullable();
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
        });

        /**
         * Keanggotaan regu, BERTANGGAL.
         *
         * Seorang pindah regu, dan riwayatnya harus tetap terbaca:
         * roster Januari disusun ketika ia masih di Regu A, dan
         * menuliskan keanggotaannya sebagai satu kolom pada pekerja
         * akan membuat roster Januari ikut berpindah begitu ia dipindah
         * pada bulan Maret — jadwal yang sudah berlalu berubah sendiri.
         */
        Schema::create('hr_regu_anggota', function (Blueprint $t) {
            $t->id();
            $t->foreignId('regu_id')->constrained('hr_regu')->cascadeOnDelete();
            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();
            $t->date('mulai');
            $t->date('selesai')->nullable();
            $t->timestamps();

            $t->index(['regu_id', 'pekerja_id']);
            $t->index(['pekerja_id', 'mulai']);
        });

        /**
         * Satu baris per orang per tanggal — hasil yang diterbitkan.
         *
         * Unik pada (pekerja_id, tanggal): seorang tidak dapat berada
         * dalam dua keadaan pada hari yang sama, dan tanpa batasan itu
         * penerbitan ulang akan menggandakan seluruh bulannya tanpa
         * satu galat pun — lalu rekap hari kerjanya berlipat, dan
         * tunjangan site ikut berlipat bersamanya.
         */
        Schema::create('hr_roster', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();
            $t->foreignId('regu_id')->nullable()->constrained('hr_regu')->nullOnDelete();
            $t->foreignId('pola_roster_id')->nullable()->constrained('hr_pola_roster')->nullOnDelete();
            $t->foreignId('blok_id')->nullable()->constrained('mnr_blok')->nullOnDelete();

            $t->date('tanggal');

            /* kerja | libur | cuti | sakit | izin */
            $t->string('keadaan', 12)->default('libur');

            /* siang | malam — kosong pada hari libur. */
            $t->string('shift', 10)->nullable();

            $t->unsignedTinyInteger('jam')->default(0);

            /* Hasil pemeriksaan kelayakan saat diterbitkan, disimpan
               apa adanya. Dihitung ulang saat dibaca, layar roster
               bulan lalu akan menampilkan blokir yang baru muncul hari
               ini — dan yang membacanya menyangka jadwal yang sudah
               berlalu itu memang melanggar. */
            $t->string('halangan', 20)->nullable();

            $t->text('catatan')->nullable();
            $t->boolean('terbit')->default(false);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();

            $t->unique(['pekerja_id', 'tanggal']);
            $t->index(['company_id', 'tanggal']);
            $t->index(['regu_id', 'tanggal']);
        });

        /**
         * Kebutuhan tenaga kerja per area — manpower plan lawan actual.
         *
         * Disimpan per jabatan, bukan sebagai satu angka per site:
         * kekurangan dua operator excavator tidak tertutup oleh
         * kelebihan tiga admin, dan satu angka gabungan menyembunyikan
         * persis kekurangan yang menghentikan produksi.
         */
        Schema::create('hr_kebutuhan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('blok_id')->nullable()->constrained('mnr_blok')->nullOnDelete();
            $t->foreignId('jabatan_id')->nullable()->constrained('mnr_jabatan')->nullOnDelete();

            $t->date('mulai');
            $t->date('selesai')->nullable();
            $t->unsignedSmallInteger('jumlah')->default(0);
            $t->string('shift', 10)->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_kebutuhan');
        Schema::dropIfExists('hr_roster');
        Schema::dropIfExists('hr_regu_anggota');
        Schema::dropIfExists('hr_regu');
        Schema::dropIfExists('hr_pola_roster');
    }
};
