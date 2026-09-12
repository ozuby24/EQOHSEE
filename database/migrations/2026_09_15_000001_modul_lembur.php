<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upah dan lembur.
 *
 * DUA TABEL, DAN PEMISAHANNYA YANG MENENTUKAN. `hr_upah` menyimpan
 * upah yang BERLAKU SEJAK sebuah tanggal; `hr_lembur` menyimpan
 * perintah lembur beserta ANGKA YANG SUDAH DIHITUNG saat ia disetujui.
 *
 * Nilainya disimpan, bukan dihitung ulang saat dibaca. Upah naik —
 * dan lembur bulan lalu yang sudah dibayar ikut berubah nilainya pada
 * layar, sehingga slip gaji yang sudah diterima tidak lagi cocok
 * dengan apa yang ditampilkan sistem. Yang disengketakan pekerja
 * adalah angka di slipnya, dan angka itu harus dapat ditunjukkan
 * kembali persis seperti saat dibayarkan.
 *
 * ACUAN — PP 35/2021, dan angkanya bukan karangan:
 *
 *   · Pasal 29 — lembur paling banyak 4 jam sehari dan 18 jam
 *     seminggu, TIDAK termasuk lembur pada hari istirahat mingguan
 *     dan hari libur resmi.
 *   · Pasal 31 ayat (1) — hari kerja: jam pertama 1,5x upah sejam,
 *     jam kedua dan seterusnya 2x.
 *   · Pasal 31 ayat (2) huruf a — hari libur, pola 6 hari kerja: jam
 *     ke-1 s.d. ke-7 2x, jam ke-8 3x, jam ke-9 dan ke-10 4x.
 *   · Pasal 31 ayat (2) huruf b — hari libur, pola 5 hari kerja: jam
 *     ke-1 s.d. ke-8 2x, jam ke-9 3x, jam ke-10 dan ke-11 4x.
 *   · Pasal 32 — upah sejam = 1/173 x upah sebulan.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * Upah yang berlaku sejak sebuah tanggal.
         *
         * BERTANGGAL-BERLAKU, bukan satu kolom pada baris pekerja.
         * Upah naik, dan lembur bulan lalu harus tetap terhitung dengan
         * upah yang berlaku saat itu. Disimpan sebagai satu kolom yang
         * ditimpa, kenaikan upah bulan ini diam-diam menaikkan nilai
         * seluruh lembur tahun lalu — dan tidak ada satu galat pun yang
         * menandainya.
         */
        Schema::create('hr_upah', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();

            $t->date('berlaku_mulai');

            /* Ketiganya terpisah karena dasar perhitungan lembur
               membedakannya (PP 35/2021 pasal 32 ayat 2 dan 3):
               tunjangan TETAP ikut dihitung penuh, tunjangan TIDAK
               TETAP tidak — dan justru perbandingan keduanya yang
               menentukan apakah dasarnya 100% atau 75%. */
            $t->decimal('pokok', 14, 2)->default(0);
            $t->decimal('tunjangan_tetap', 14, 2)->default(0);
            $t->decimal('tunjangan_tidak_tetap', 14, 2)->default(0);

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['pekerja_id', 'berlaku_mulai']);
            $t->index(['company_id', 'berlaku_mulai']);
        });

        /**
         * Surat perintah lembur, beserta hasil hitungannya.
         *
         * RINCIAN FAKTORNYA IKUT DISIMPAN, bukan hanya jumlahnya.
         * Sengketa upah lembur selalu berbentuk "kenapa angkanya
         * segini" — dan jawabannya adalah berapa jam dikalikan faktor
         * berapa, bukan satu angka akhir. Disimpan sebagai satu nilai,
         * yang menjawabnya harus menghitung ulang dengan aturan yang
         * mungkin sudah berubah.
         */
        Schema::create('hr_lembur', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();

            /* Catatan absensi yang mendasarinya, bila ada. Lembur yang
               tidak dapat ditelusuri ke jam yang benar-benar tercatat
               adalah lembur yang hanya bersandar pada ingatan
               pengawasnya. */
            $t->foreignId('absensi_id')->nullable()->constrained('hr_absensi')->nullOnDelete();

            $t->date('tanggal');

            /* kerja | libur — menentukan tabel faktor mana yang
               dipakai, dan apakah batas 4 jam berlaku. */
            $t->string('jenis_hari', 6)->default('kerja');

            /* Pola minggu yang berlaku bagi orang ini: 5 atau 6 hari
               kerja. Faktor lembur hari libur berbeda di antara
               keduanya (pasal 31 ayat 2 huruf a dan b). */
            $t->unsignedTinyInteger('hari_seminggu')->default(6);

            $t->decimal('jam', 5, 2)->default(0);

            /* Upah sebulan yang dipakai sebagai dasar, DAN persen
               dasarnya (100 atau 75). Keduanya disimpan sebab pasal 32
               ayat (3) memilih di antara keduanya menurut susunan upah
               orang itu pada saat itu. */
            $t->decimal('upah_sebulan', 14, 2)->default(0);
            $t->unsignedTinyInteger('dasar_persen')->default(100);
            $t->decimal('upah_sejam', 14, 2)->default(0);

            /* Rincian jam x faktor, apa adanya. */
            $t->json('rincian')->nullable();

            $t->decimal('nilai', 14, 2)->default(0);

            $t->text('alasan')->nullable();

            /* menunggu | disetujui | ditolak | dibatalkan */
            $t->string('status', 12)->default('menunggu');

            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();

            $t->foreignId('ditindak_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditindak_pada')->nullable();
            $t->text('catatan_tindak')->nullable();

            $t->timestamps();

            $t->unique(['pekerja_id', 'tanggal']);
            $t->index(['company_id', 'tanggal']);
            $t->index(['company_id', 'status']);
        });

        /**
         * Jabatan yang DIKECUALIKAN dari upah lembur.
         *
         * PP 35/2021 pasal 27 ayat (4): ketentuan waktu kerja lembur
         * tidak berlaku bagi pekerja golongan jabatan tertentu yang
         * bertanggung jawab sebagai pemikir, perencana, pelaksana, dan
         * pengendali jalannya perusahaan — upahnya sudah lebih tinggi
         * justru karena itu.
         *
         * Ditaruh pada jabatannya, bukan pada orangnya: yang
         * dikecualikan adalah golongan jabatan, dan seseorang yang
         * naik jabatan ikut berpindah golongan tanpa ada yang perlu
         * menyunting barisnya sendiri.
         */
        Schema::table('mnr_jabatan', function (Blueprint $t) {
            $t->boolean('kecuali_lembur')->default(false)->after('nama');
        });
    }

    public function down(): void
    {
        Schema::table('mnr_jabatan', fn (Blueprint $t) => $t->dropColumn('kecuali_lembur'));

        Schema::dropIfExists('hr_lembur');
        Schema::dropIfExists('hr_upah');
    }
};
