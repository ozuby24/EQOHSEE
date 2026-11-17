<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Temuan bahaya punya batas akhir perbaikannya.
 *
 * Register Tindakan Perbaikan — lembar yang diserahkan kepada pengawas
 * dan dibawa ke rapat bulanan — menuntut satu kolom yang tidak pernah
 * ada di skema ini: sampai kapan temuannya harus ditutup.
 *
 * Ketiadaannya bukan soal kolom yang kurang. Tanpa batas akhir, "OPEN"
 * pada sebuah temuan tidak dapat dibedakan antara yang baru dilaporkan
 * kemarin dan yang sudah lewat tenggat tiga minggu; keduanya tergambar
 * sebagai lencana oranye yang sama. Rapat yang memakai daftar itu
 * karena itu membahas urutan yang salah — yang paling lama menganggur
 * tenggelam di antara yang baru masuk.
 *
 * BOLEH KOSONG. Seluruh temuan yang sudah ada dibuat sebelum kolom ini
 * ada, dan menebakkan tanggal bagi mereka berarti menerbitkan tenggat
 * yang tidak pernah disepakati siapa pun — pada lembar yang dipakai
 * menagih orang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hazard_reports', function (Blueprint $t) {
            $t->date('batas_akhir')->nullable()->after('rekomendasi');

            /* Indeksnya berpasangan dengan status, bukan sendirian.
               Yang ditanyakan halaman monitor selalu keduanya sekaligus
               — "yang BELUM ditutup dan sudah lewat tenggat" — dan
               indeks atas satu kolom saja meninggalkan separuh
               penyaringan dikerjakan baris demi baris. */
            $t->index(['status', 'batas_akhir'], 'hazard_status_tenggat_idx');
        });
    }

    public function down(): void
    {
        Schema::table('hazard_reports', function (Blueprint $t) {
            $t->dropIndex('hazard_status_tenggat_idx');
            $t->dropColumn('batas_akhir');
        });
    }
};
