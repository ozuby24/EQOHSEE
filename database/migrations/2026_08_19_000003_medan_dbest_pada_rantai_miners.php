<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Medan dan tautan yang dibaca dari D'Best, untuk keempat tahap Miners.
 *
 * ═══ YANG PALING PENTING: RANTAINYA MENJADI EKSPLISIT ═══
 *
 * D'Best menautkan tiap tahap ke BARIS tahap sebelumnya —
 * `mine_permits.mcu_details_id`, `.induction_details_id`, dan
 * `form_simpers.mine_permits_id`. EQOHSEE selama ini menurunkannya
 * secara tersirat: dasar sebuah permit dianggap "MCU terakhir orang
 * itu", dihitung ulang tiap kali dibaca.
 *
 * Selisihnya baru terasa saat MCU baru datang. Yang tersirat langsung
 * berpindah menunjuk MCU yang baru, sehingga catatan tentang atas dasar
 * apa permit itu DITERBITKAN hilang tanpa jejak — dan itulah persis
 * pertanyaan yang diajukan auditor ketika sebuah permit dipersoalkan.
 * Yang eksplisit tetap menunjuk MCU yang dipakai saat penerbitan, dan
 * perbedaan antara keduanya justru menjadi temuan yang dapat dilihat.
 *
 * Tautannya nullable dan nullOnDelete: data lama tidak punya rantai
 * ini, dan menolak baris yang tidak punya berarti membuang riwayat yang
 * sah hanya karena dicatat sebelum aturannya ada.
 *
 * ═══ MEDAN MCU ═══
 *
 * `usia` disimpan, bukan dihitung dari tanggal lahir: yang tercetak
 * pada surat MCU adalah usia saat pemeriksaan, dan menghitungnya ulang
 * tahun depan memberi angka yang berbeda dari suratnya.
 *
 * `mcu_berikutnya` (D'Best: `nom`) berbeda dari masa berlaku. Masa
 * berlaku menyatakan sampai kapan hasilnya sah; jadwal berikutnya
 * menyatakan kapan orangnya harus diperiksa lagi — kerap lebih awal,
 * karena antrean klinik dan jadwal cuti.
 *
 * `status_verifikasi` memisahkan "hasilnya sudah masuk" dari "hasilnya
 * sudah diperiksa kebenarannya". Tanpa pemisahan itu, berkas yang baru
 * diunggah kontraktor terbaca sama sahnya dengan yang sudah diverifikasi
 * paramedis.
 *
 * `catatan_kontraktor` terpisah dari `catatan` milik pengelola. Satu
 * kolom catatan yang dipakai dua pihak berakhir sebagai percakapan yang
 * saling menimpa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paspor_mcu', function (Blueprint $t) {
            foreach ([
                'usia'               => fn () => $t->unsignedTinyInteger('usia')->nullable(),
                'mcu_berikutnya'     => fn () => $t->date('mcu_berikutnya')->nullable(),
                'status_verifikasi'  => fn () => $t->string('status_verifikasi', 30)->nullable(),
                'catatan_kontraktor' => fn () => $t->text('catatan_kontraktor')->nullable(),
                'remarks'            => fn () => $t->text('remarks')->nullable(),
            ] as $kolom => $buat) {
                if (!Schema::hasColumn('paspor_mcu', $kolom)) $buat();
            }
        });

        Schema::table('paspor_induksi', function (Blueprint $t) {
            if (!Schema::hasColumn('paspor_induksi', 'berkas_permohonan')) {
                $t->string('berkas_permohonan')->nullable();
            }

            if (!Schema::hasColumn('paspor_induksi', 'paspor_mcu_id')) {
                $t->foreignId('paspor_mcu_id')->nullable()
                  ->constrained('paspor_mcu')->nullOnDelete();
            }
        });

        Schema::table('paspor_kartu', function (Blueprint $t) {
            foreach ([
                'tgl_lahir'     => fn () => $t->date('tgl_lahir')->nullable(),
                'foto'          => fn () => $t->string('foto')->nullable(),
                'subkontraktor' => fn () => $t->string('subkontraktor', 150)->nullable(),
                'jenis_sim'     => fn () => $t->string('jenis_sim', 30)->nullable(),
            ] as $kolom => $buat) {
                if (!Schema::hasColumn('paspor_kartu', $kolom)) $buat();
            }

            if (!Schema::hasColumn('paspor_kartu', 'paspor_mcu_id')) {
                $t->foreignId('paspor_mcu_id')->nullable()
                  ->constrained('paspor_mcu')->nullOnDelete();
            }

            if (!Schema::hasColumn('paspor_kartu', 'paspor_induksi_id')) {
                $t->foreignId('paspor_induksi_id')->nullable()
                  ->constrained('paspor_induksi')->nullOnDelete();
            }

            /* SIMPER berdiri di atas Mine Permit — kartu yang menunjuk
               kartu lain. Dinamai `kartu_dasar_id`, bukan
               `mine_permit_id`, sebab yang menentukan perannya jenis
               kartunya, bukan nama kolomnya. */
            if (!Schema::hasColumn('paspor_kartu', 'kartu_dasar_id')) {
                $t->foreignId('kartu_dasar_id')->nullable()
                  ->constrained('paspor_kartu')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('paspor_kartu', function (Blueprint $t) {
            foreach (['paspor_mcu_id', 'paspor_induksi_id', 'kartu_dasar_id'] as $k) {
                if (Schema::hasColumn('paspor_kartu', $k)) {
                    $t->dropConstrainedForeignId($k);
                }
            }

            foreach (['tgl_lahir', 'foto', 'subkontraktor', 'jenis_sim'] as $k) {
                if (Schema::hasColumn('paspor_kartu', $k)) $t->dropColumn($k);
            }
        });

        Schema::table('paspor_induksi', function (Blueprint $t) {
            if (Schema::hasColumn('paspor_induksi', 'paspor_mcu_id')) {
                $t->dropConstrainedForeignId('paspor_mcu_id');
            }

            if (Schema::hasColumn('paspor_induksi', 'berkas_permohonan')) {
                $t->dropColumn('berkas_permohonan');
            }
        });

        Schema::table('paspor_mcu', function (Blueprint $t) {
            foreach (['usia', 'mcu_berikutnya', 'status_verifikasi',
                      'catatan_kontraktor', 'remarks'] as $k) {
                if (Schema::hasColumn('paspor_mcu', $k)) $t->dropColumn($k);
            }
        });
    }
};
