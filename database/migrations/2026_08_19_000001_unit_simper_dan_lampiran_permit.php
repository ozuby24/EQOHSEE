<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua kekurangan pada kartu tambang, dibaca dari D'Best.
 *
 * ═══ 1 · SIMPER dinilai PER UNIT, bukan per orang ═══
 *
 * Satu SIMPER menyebut unit apa saja yang boleh dikemudikan orang itu,
 * dan tiap unit punya penilaiannya sendiri: nilai P2H, nilai praktek,
 * berkas rambu, berkas teori, hasil praktek, dan evaluasi. Seorang
 * operator dapat lulus untuk Excavator PC 200 dan belum lulus untuk
 * PC 500 — dua baris, satu kartu.
 *
 * Disimpan sebagai satu baris per kartu, seluruh nilai itu bertumpuk
 * menjadi satu angka yang tidak menyebut unit mana. Yang hilang bukan
 * kerapian melainkan dasar izinnya: kartu yang menyebut "Excavator"
 * tanpa merinci tipe membolehkan orang mengemudikan unit yang tidak
 * pernah diujikan kepadanya, dan tidak ada satu pun catatan yang
 * menunjukkan itu terjadi.
 *
 * `authority` menyimpan kelas kewenangannya (D'Best memakai huruf
 * tunggal, mis. F dan T). Dibiarkan sebagai teks pendek: artinya
 * ditetapkan situs, dan daftar tertutup yang ditebak dari dua contoh
 * akan menolak huruf ketiga yang sah.
 *
 * ═══ 2 · Mine Permit punya lampiran yang belum ada tempatnya ═══
 *
 * SPDK, form departemen khusus, LOTO/sertifikat welder, dan sertifikat
 * training blasting. Keempatnya syarat yang diperiksa sebelum permit
 * terbit — bukan berkas pelengkap. Tanpa tempat menyimpannya, satu-
 * satunya cara mencatat bahwa syaratnya sudah dipenuhi adalah
 * menuliskannya di kolom catatan, yang tidak dapat dicari, tidak dapat
 * dihitung, dan tidak dapat dibuka kembali saat auditor memintanya.
 *
 * Ditambahkan pula golongan darah, telepon, kontak darurat, dan KTP —
 * empat medan yang tercetak pada kartunya sendiri dan karena itu harus
 * ada sebelum kartunya dapat dicetak.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paspor_kartu_unit')) {
            Schema::create('paspor_kartu_unit', function (Blueprint $t) {
                $t->id();

                $t->foreignId('paspor_kartu_id')->constrained('paspor_kartu')->cascadeOnDelete();

                /* Master unit dipakai bila ada padanannya, tetapi tidak
                   diwajibkan: unit sewa dan unit subkontraktor kerap belum
                   terdaftar, dan menolak barisnya berarti orang yang sudah
                   diuji tidak dapat dicatat sama sekali. */
                $t->foreignId('ko_unit_master_id')->nullable()
                  ->constrained('ko_unit_master')->nullOnDelete();

                $t->string('authority', 10)->nullable();
                $t->string('jenis_unit', 120)->nullable();
                $t->string('type_merk', 200)->nullable();

                $t->unsignedTinyInteger('nilai_p2h')->nullable();
                $t->unsignedTinyInteger('nilai_praktek')->nullable();

                $t->string('berkas_rambu')->nullable();
                $t->string('berkas_teori')->nullable();
                $t->string('hasil_praktek')->nullable();
                $t->string('evaluasi')->nullable();

                $t->text('catatan')->nullable();
                $t->timestamps();

                $t->index('paspor_kartu_id');
            });
        }

        Schema::table('paspor_kartu', function (Blueprint $t) {
            foreach ([
                'golongan_darah'   => fn () => $t->string('golongan_darah', 5)->nullable(),
                'telepon'          => fn () => $t->string('telepon', 30)->nullable(),
                'kontak_darurat'   => fn () => $t->string('kontak_darurat', 120)->nullable(),
                'berkas_ktp'       => fn () => $t->string('berkas_ktp')->nullable(),
                'berkas_permohonan' => fn () => $t->string('berkas_permohonan')->nullable(),

                /* Lampiran syarat permit. */
                'berkas_spdk'      => fn () => $t->string('berkas_spdk')->nullable(),
                'berkas_dept'      => fn () => $t->string('berkas_dept')->nullable(),
                'berkas_lotto'     => fn () => $t->string('berkas_lotto')->nullable(),
                'berkas_blasting'  => fn () => $t->string('berkas_blasting')->nullable(),
            ] as $kolom => $buat) {
                if (!Schema::hasColumn('paspor_kartu', $kolom)) $buat();
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paspor_kartu_unit');

        Schema::table('paspor_kartu', function (Blueprint $t) {
            foreach ([
                'golongan_darah', 'telepon', 'kontak_darurat', 'berkas_ktp', 'berkas_permohonan',
                'berkas_spdk', 'berkas_dept', 'berkas_lotto', 'berkas_blasting',
            ] as $kolom) {
                if (Schema::hasColumn('paspor_kartu', $kolom)) $t->dropColumn($kolom);
            }
        });
    }
};
