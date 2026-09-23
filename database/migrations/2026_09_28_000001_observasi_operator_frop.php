<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Observasi operator loader — FROP (Field Reliability & Operator
 * Performance), Operation People Development.
 *
 * Satu baris frop_observasi adalah satu sesi observasi seorang operator
 * excavator di satu front: komponen cycle time yang diukur dengan
 * stopwatch, kondisi front dan material, produktivitas jam itu, dan
 * temuan beserta tindakan perbaikannya. Isinya mengikuti sheet
 * "Akumulatif Observasi" berkas kerja OPD, kolom demi kolom, ditambah
 * butir yang dijelaskan Panduan Pengisian tetapi belum punya kolom di
 * sana (MTO, kondisi permukaan, boulder, sudut pass pertama).
 *
 * ── Yang TIDAK disimpan ──
 *
 * Aktual CT, selisih, status, kesimpulan, dan rekomendasi coaching
 * semuanya turunan dari komponen yang disimpan, dan dihitung
 * App\Support\Frop\Penilaian setiap kali dibaca. Disimpan, angka itu
 * akan tertinggal ketika satu komponen diperbaiki — dan berkas asalnya
 * sendiri memperlihatkan akibatnya: kolom yang bertentangan dengan
 * kolom di sebelahnya.
 *
 * ── Plan CT DISIMPAN, walau dapat diturunkan dari level material ──
 *
 * Ia patokan yang berlaku pada hari observasi. Bila acuan Plan CT
 * direvisi, sesi lama tetap dinilai terhadap patokan yang dipakai saat
 * operatornya diamati, bukan tiba-tiba berubah dari ON TARGET menjadi
 * OVER tanpa satu pun data yang disentuh.
 *
 * ── Tindakan perbaikan menempel pada sesinya ──
 *
 * Sheet "Problem & CA Tracker" di berkas asalnya seluruhnya rumus yang
 * menyalin kolom temuan dari Akumulatif Observasi. Dua tabel yang satu
 * menyalin yang lain adalah dua tempat yang harus dijaga tetap sama;
 * di sini PIC, tenggat, dan tanggal selesai tinggal pada baris yang
 * sama dengan temuannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frop_observasi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            /* Waktu */
            $t->date('tanggal');
            $t->unsignedTinyInteger('shift')->nullable();          // 1 | 2 | 3
            $t->string('jam_observasi', 40)->nullable();           // "09.00 – 10.00"

            /* Identitas */
            $t->string('unit', 60);                                // "Ex 699 / PC 1250"
            $t->string('operator', 120);
            $t->foreignId('pekerja_id')->nullable()->constrained('mnr_pekerja')->nullOnDelete();
            $t->string('gl_front', 120)->nullable();
            $t->string('observer', 120)->nullable();
            $t->string('verified_by', 120)->nullable();

            /* Kondisi & operasi */
            $t->string('kondisi_mesin', 40)->nullable();
            $t->string('mode_kerja', 40)->nullable();
            $t->string('level', 10);                               // easy | average | severe
            $t->string('material', 80)->nullable();
            $t->string('metode_posisi', 80)->nullable();
            $t->string('operating_condition', 20)->nullable();
            $t->string('metode_loading', 40)->nullable();
            $t->string('mto', 10)->nullable();                     // MTO | Non-MTO
            $t->decimal('tinggi_jenjang', 5, 2)->nullable();
            $t->decimal('lebar_front', 6, 2)->nullable();

            /* Front condition tracker */
            $t->string('kondisi_permukaan', 20)->nullable();       // Normal | Undulating | Irregular
            $t->boolean('boulder')->nullable();
            $t->string('sudut_pass', 20)->nullable();              // "90°", "<90°"
            $t->string('cuaca', 20)->nullable();

            /* Komponen cycle time, detik */
            $t->decimal('spotting', 6, 2)->nullable();
            $t->decimal('digging', 6, 2)->nullable();
            $t->decimal('swl', 6, 2)->nullable();
            $t->decimal('dump', 6, 2)->nullable();
            $t->decimal('swe', 6, 2)->nullable();
            $t->decimal('plan_ct', 5, 1);

            /* Loading ke satu hauler */
            $t->unsignedSmallInteger('loading_detik')->nullable();
            $t->string('n_passing', 20)->nullable();               // "5", "5-6"
            $t->boolean('bucket_heap')->nullable();

            /* Produktivitas jam observasi, BCM */
            $t->unsignedInteger('target_pty')->nullable();
            $t->unsignedInteger('aktual_pty')->nullable();

            /* Temuan & tindakan perbaikan */
            $t->text('temuan')->nullable();
            $t->text('corrective_action')->nullable();
            $t->string('status_ca', 20)->default('Open');          // Open | In Progress | Closed
            $t->string('pic_ca', 120)->nullable();
            $t->date('deadline_ca')->nullable();
            $t->date('selesai_ca')->nullable();

            $t->text('catatan')->nullable();
            $t->string('sumber', 120)->nullable();                 // nama berkas bila hasil impor
            $t->timestamps();

            $t->index(['company_id', 'tanggal']);
            $t->index(['company_id', 'operator']);
        });

        /* Coaching Log. Satu baris satu pendampingan; boleh merujuk sesi
           observasi yang memicunya, boleh tidak — coaching kelas atau
           yang diminta GL tidak lahir dari satu sesi. */
        Schema::create('frop_coaching', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('observasi_id')->nullable()->constrained('frop_observasi')->nullOnDelete();

            $t->date('tanggal');
            $t->string('operator', 120);
            $t->string('unit', 60)->nullable();
            $t->text('materi');
            $t->text('respons')->nullable();
            $t->string('coach', 120)->nullable();
            $t->text('follow_up')->nullable();
            $t->date('target_selesai')->nullable();
            $t->string('status', 20)->default('Open');             // Open | In Progress | Closed
            $t->timestamps();

            $t->index(['company_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frop_coaching');
        Schema::dropIfExists('frop_observasi');
    }
};
