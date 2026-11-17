<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Absensi — dan rekonsiliasinya terhadap roster.
 *
 * DUA LAPIS, DAN PEMISAHANNYA YANG MENENTUKAN:
 *
 *   `hr_absensi_jejak` menyimpan PERISTIWA — tiap pindaian sidik jari,
 *   tiap ketukan tombol di ponsel. Hanya ditambah, tidak pernah
 *   disunting. Itu buktinya.
 *
 *   `hr_absensi` menyimpan CATATAN HARIAN yang diturunkan darinya —
 *   satu baris per orang per tanggal, beserta hasil rekonsiliasinya
 *   terhadap roster. Itu yang dibaca dan yang boleh dikoreksi.
 *
 * Digabung menjadi satu tabel, koreksi jam masuk MENGHAPUS pindaian
 * aslinya — dan pertanyaan pertama auditor ("jam berapa alatnya
 * mencatat orang ini") kehilangan jawabannya justru pada baris yang
 * dipersoalkan. Digabung pula, pengiriman ulang batch luring tidak
 * punya tempat untuk menyimpan kunci idempotennya.
 *
 * SINYAL SITE MEMANG PUTUS-PUTUS, dan itu bukan keadaan luar biasa
 * melainkan keadaan biasa. Karena itu tiap peristiwa membawa KUNCI
 * IDEMPOTEN yang dibuat di sisi pengirim: batch yang sama dikirim
 * berkali-kali sampai satu kali berhasil, dan yang kedua tidak
 * menambah apa pun. Tanpa itu, satu hari dengan sinyal buruk
 * menghasilkan lima salinan tiap pindaian — dan rekap jam kerjanya
 * berlipat lima tanpa satu galat pun.
 *
 * ACUAN:
 *   · Kepmenakertrans KEP.234/MEN/2003 — batas jam kerja yang
 *     dibandingkan dengan jam yang benar-benar tercatat.
 *   · UU 27/2022 (PDP) — data biometrik adalah data pribadi spesifik.
 *     Yang disimpan di sini BUKAN gambar mentah melainkan rujukan
 *     template pada alatnya; swafoto ponsel disimpan pada disk
 *     tertutup dan boleh kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * Mesin absensi di lapangan.
         *
         * BERTOKEN SENDIRI, bukan memakai akun pengguna. Alat pindai
         * tidak punya orang yang masuk ke dalamnya; ia mengirim ke
         * endpoint dengan kuncinya sendiri, dan begitulah push SDK
         * ZKTeco dan Hikvision bekerja. Dipaksa memakai token pengguna,
         * kredensial seorang manusia harus ditanam di dalam alat yang
         * dipasang di pos jaga.
         */
        Schema::create('hr_mesin_absensi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('blok_id')->nullable()->constrained('mnr_blok')->nullOnDelete();

            $t->string('nama', 120);

            /* zkteco | hikvision | lainnya */
            $t->string('merek', 20)->default('lainnya');

            $t->string('nomor_seri', 80)->nullable();
            $t->string('ip', 45)->nullable();

            /* Disimpan sebagai HASH, tidak pernah sebagai teks terang.
               Alat yang dicuri dari pos jaga membawa tokennya; yang
               tersimpan di sini tidak boleh dapat dibaca balik oleh
               siapa pun yang membuka basis datanya. */
            $t->string('token_hash', 100)->nullable();

            $t->timestamp('terakhir_hubung')->nullable();
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'aktif']);
        });

        /**
         * Peristiwa mentah — hanya ditambah, tidak pernah disunting.
         *
         * Inilah buktinya. Koreksi apa pun terjadi pada catatan harian
         * di bawah, bukan di sini: pertanyaan pertama auditor adalah
         * "jam berapa alatnya mencatat orang ini", dan jawabannya harus
         * tetap ada sesudah jamnya dikoreksi.
         */
        Schema::create('hr_absensi_jejak', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();
            $t->foreignId('mesin_id')->nullable()->constrained('hr_mesin_absensi')->nullOnDelete();

            /* Saat peristiwanya TERJADI, bukan saat ia sampai. Keduanya
               berbeda berjam-jam pada pengiriman luring, dan yang
               menentukan hari kerja adalah yang pertama. */
            $t->timestamp('terjadi');
            $t->timestamp('diterima')->nullable();

            /* masuk | keluar */
            $t->string('arah', 10);

            /* mesin | ponsel | manual */
            $t->string('sumber', 10)->default('mesin');

            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();

            /* Jarak ke titik area kerjanya, dalam meter. Disimpan
               sebagai hasil hitungan saat diterima: titik geofence-nya
               dapat digeser kemudian, dan menghitung ulang akan
               mengubah keputusan yang sudah diambil atas data lama. */
            $t->unsignedInteger('jarak_m')->nullable();
            $t->boolean('dalam_area')->nullable();

            /* Swafoto ponsel. Disk TERTUTUP, dan boleh kosong: UU PDP
               memperlakukan wajah sebagai data pribadi spesifik, dan
               menuntutnya pada tiap pindaian berarti mengumpulkan lebih
               banyak daripada yang diperlukan. */
            $t->string('berkas_swafoto')->nullable();

            /* Rujukan template biometrik pada alatnya — BUKAN gambar
               mentah. Yang disimpan hanyalah penunjuk; templatnya
               tinggal di dalam alat. */
            $t->string('rujukan_biometrik', 100)->nullable();

            /* Dicatat saat sinyal putus lalu dikirim menyusul. */
            $t->boolean('luring')->default(false);

            /**
             * Kunci idempoten, dibuat SISI PENGIRIM.
             *
             * Unik seluruh tabel. Batch luring dikirim berkali-kali
             * sampai satu kali berhasil, dan yang kedua tidak menambah
             * apa pun. Tanpa ini, satu hari bersinyal buruk
             * menghasilkan lima salinan tiap pindaian — dan rekap jam
             * kerjanya berlipat lima tanpa satu galat pun.
             */
            $t->string('kunci', 100)->unique();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'terjadi']);
            $t->index(['pekerja_id', 'terjadi']);
        });

        /**
         * Catatan harian — satu baris per orang per tanggal.
         *
         * DITURUNKAN dari jejaknya, lalu direkonsiliasi terhadap
         * roster. Yang disimpan di sini adalah kesimpulannya: jam
         * masuk, jam keluar, berapa jam, dan apakah itu sesuai dengan
         * yang dijadwalkan.
         */
        Schema::create('hr_absensi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();
            $t->foreignId('roster_id')->nullable()->constrained('hr_roster')->nullOnDelete();
            $t->foreignId('blok_id')->nullable()->constrained('mnr_blok')->nullOnDelete();

            $t->date('tanggal');

            $t->timestamp('masuk')->nullable();
            $t->timestamp('keluar')->nullable();

            /* Menit keterlambatan terhadap jam mulai shiftnya. Disimpan
               sebagai angka, bukan disimpulkan saat dibaca: jam mulai
               shift dapat diubah kemudian, dan menghitung ulang akan
               mengubah catatan keterlambatan yang sudah menjadi dasar
               tindakan. */
            $t->integer('telat_menit')->nullable();

            /* Jam kerja yang benar-benar tercatat, dua desimal. */
            $t->decimal('jam', 5, 2)->default(0);

            /* hadir | terlambat | absen | luar_roster | belum_pulang */
            $t->string('keadaan', 16)->default('absen');

            /* mesin | ponsel | manual | campuran */
            $t->string('sumber', 10)->nullable();

            $t->boolean('luring')->default(false);
            $t->boolean('dalam_area')->nullable();

            /* Koreksi manusia, beserta siapa dan kapan. Jejak aslinya
               tetap utuh di tabel di atas. */
            $t->boolean('dikoreksi')->default(false);
            $t->foreignId('dikoreksi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('dikoreksi_pada')->nullable();
            $t->text('alasan_koreksi')->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['pekerja_id', 'tanggal']);
            $t->index(['company_id', 'tanggal']);
            $t->index(['blok_id', 'tanggal']);
        });

        /* Geofence area kerja. Ditaruh pada bloknya sendiri, bukan
           sebagai tabel tersendiri: satu area kerja punya satu titik
           dan satu jari-jari, dan tabel berisi satu baris per blok
           hanyalah blok dengan langkah tambahan. */
        Schema::table('mnr_blok', function (Blueprint $t) {
            $t->decimal('lat', 10, 7)->nullable()->after('nama');
            $t->decimal('lng', 10, 7)->nullable()->after('lat');

            /* Jari-jari geofence dalam meter. NULL berarti area ini
               memang tidak dipagari — absen ponsel dari mana pun
               diterima, dan itu keputusan yang sah bagi kantor pusat. */
            $t->unsignedInteger('radius_m')->nullable()->after('lng');
        });

        /* Jam mulai shift, dipakai menghitung keterlambatan. Ditaruh
           pada polanya karena di situlah jam kerja sehari sudah
           tersimpan — memisahkannya berarti dua tempat yang harus
           diubah bersama tiap kali jadwal shift bergeser. */
        Schema::table('hr_pola_roster', function (Blueprint $t) {
            $t->time('mulai_siang')->nullable()->after('shift');
            $t->time('mulai_malam')->nullable()->after('mulai_siang');

            /* Toleransi keterlambatan dalam menit. Nol berarti tepat
               waktu dituntut ke menitnya, dan itu jarang yang
               dimaksudkan — bus jemputan pun terlambat. */
            $t->unsignedSmallInteger('toleransi_menit')->default(15)->after('mulai_malam');
        });
    }

    public function down(): void
    {
        Schema::table('hr_pola_roster', function (Blueprint $t) {
            $t->dropColumn(['mulai_siang', 'mulai_malam', 'toleransi_menit']);
        });

        Schema::table('mnr_blok', function (Blueprint $t) {
            $t->dropColumn(['lat', 'lng', 'radius_m']);
        });

        Schema::dropIfExists('hr_absensi');
        Schema::dropIfExists('hr_absensi_jejak');
        Schema::dropIfExists('hr_mesin_absensi');
    }
};
