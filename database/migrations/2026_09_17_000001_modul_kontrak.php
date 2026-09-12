<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kontrak kerja: PKWT dan PKWTT.
 *
 * PERPANJANGAN ADALAH BARIS BARU YANG MENUNJUK INDUKNYA, bukan tanggal
 * selesai yang digeser. Batas lima tahun PP 35/2021 pasal 8 berlaku
 * atas SELURUH rangkaian PKWT beserta perpanjangannya, bukan atas tiap
 * kontrak sendiri-sendiri — dan uang kompensasi pasal 17 jatuh tempo
 * pada akhir tiap periode, bukan sekali di ujung. Digeser tanggalnya,
 * kedua hal itu hilang bersamaan: rangkaian tujuh tahun terbaca sebagai
 * satu kontrak tujuh tahun yang tidak pernah melanggar apa pun, dan
 * kompensasi yang seharusnya dibayar tiga kali dibayar sekali.
 *
 * UPAHNYA TIDAK DISIMPAN DI SINI. Dasar uang kompensasi adalah upah
 * pokok ditambah tunjangan tetap (pasal 16 ayat 4), dan angka itu sudah
 * punya satu sumber yang bertanggal: `hr_upah`. Disalin ke sini pada
 * saat tanda tangan, ia membeku — kenaikan upah di tengah kontrak tidak
 * ikut, dan kompensasinya dihitung dari angka yang sudah tidak berlaku.
 *
 * ACUAN:
 *   · PP 35/2021 pasal 5      — alasan yang sah untuk PKWT jangka waktu.
 *   · PP 35/2021 pasal 8      — jangka waktu keseluruhan paling lama 5 tahun.
 *   · PP 35/2021 pasal 10     — PKWT harian dan batas 21 hari sebulan.
 *   · PP 35/2021 pasal 12     — PKWT tidak boleh mensyaratkan masa percobaan.
 *   · PP 35/2021 pasal 14     — pencatatan ke instansi ketenagakerjaan.
 *   · PP 35/2021 pasal 15-17  — uang kompensasi akhir kontrak.
 *   · UU 13/2003 pasal 60     — masa percobaan PKWTT paling lama 3 bulan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hr_kontrak', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();

            $t->string('nomor', 60);

            /* pkwt_jangka | pkwt_selesai | pkwt_harian | pkwtt */
            $t->string('jenis', 14);

            /**
             * Alasan menurut pasal 5, wajib bagi PKWT jangka waktu.
             *
             * PKWT tanpa salah satu alasan ini bukan PKWT yang cacat
             * administrasinya — ia PKWTT sejak hari pertama, dan yang
             * baru mengetahuinya adalah pengadilan hubungan industrial.
             */
            $t->string('alasan', 20)->nullable();

            $t->date('mulai');

            /* NULL untuk PKWTT dan untuk PKWT yang diikat selesainya
               pekerjaan — keduanya memang tidak punya tanggal akhir. */
            $t->date('selesai')->nullable();

            /* Pasal 10 ayat (1): batasan suatu pekerjaan dinyatakan
               selesai harus tertulis di dalam perjanjiannya. Tanpa itu,
               tidak ada yang dapat menyatakan kontraknya berakhir. */
            $t->text('batasan_selesai')->nullable();

            /**
             * Kontrak yang diperpanjang oleh baris ini.
             *
             * Rantainya yang menentukan batas lima tahun, bukan barisnya
             * sendiri.
             */
            $t->foreignId('induk_id')->nullable()->constrained('hr_kontrak')->nullOnDelete();

            /* 1 = kontrak asal, 2 = perpanjangan pertama, dan seterusnya. */
            $t->unsignedTinyInteger('urutan')->default(1);

            /* Pasal 12 melarangnya pada PKWT; UU 13/2003 pasal 60
               membatasinya 3 bulan pada PKWTT. Disimpan supaya
               pelanggarannya dapat DILIHAT, bukan ditolak diam-diam. */
            $t->unsignedSmallInteger('masa_percobaan_hari')->default(0);

            /* draft | berjalan | selesai | diputus | jadi_pkwtt */
            $t->string('status', 12)->default('draft');

            /* Pasal 14: paling lama 3 hari kerja sejak penandatanganan. */
            $t->date('ditandatangani_pada')->nullable();
            $t->date('dicatatkan_pada')->nullable();

            /**
             * Uang kompensasi, beserta dasar dan saat hitungnya.
             *
             * Dasarnya ikut disimpan karena sengketa kompensasi selalu
             * berbentuk "kenapa segini": upah mana yang dipakai, dan
             * masa kerja berapa bulan. Satu angka akhir tidak dapat
             * menjawab keduanya.
             */
            $t->decimal('kompensasi_upah', 14, 2)->nullable();
            $t->decimal('kompensasi_bulan', 8, 4)->nullable();
            $t->decimal('kompensasi_nilai', 14, 2)->nullable();
            $t->timestamp('kompensasi_dihitung_pada')->nullable();
            $t->date('kompensasi_dibayar_pada')->nullable();

            $t->string('berkas_naskah')->nullable();
            $t->text('catatan')->nullable();

            $t->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->unique(['company_id', 'nomor']);
            $t->index(['company_id', 'status']);
            $t->index(['pekerja_id', 'mulai']);
            $t->index(['company_id', 'selesai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_kontrak');
    }
};
