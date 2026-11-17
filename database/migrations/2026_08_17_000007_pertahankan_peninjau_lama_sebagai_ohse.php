<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Yang sudah berhak meninjau tidak boleh kehilangan haknya diam-diam.
 *
 * Wewenang memutuskan di modul Miners dipersempit dari "administrator
 * ATAU Kepala Teknik Tambang" menjadi "administrator ATAU tim OHSE".
 * Penyempitannya benar dan diminta — tetapi kolom ohse_role lahir
 * kosong, sehingga TIDAK SEORANG PUN memilikinya pada pemasangan yang
 * sudah berjalan.
 *
 * Akibatnya persis kebuntuan: KTT kehilangan haknya, tidak ada yang
 * mendapat hak OHSE, dan yang tersisa hanya administrator. Bila
 * administrator itu sendiri yang mengajukan — hal biasa pada pemasangan
 * satu orang — ia pun tertahan oleh aturan "pengaju bukan peninjau",
 * dan tidak satu pun pengajuan dapat diputuskan oleh siapa pun. Tombol
 * setujuinya tidak muncul, dan tanpa keterangan apa-apa hal itu terbaca
 * sebagai sistem yang rusak.
 *
 * Cara bakunya memperkenalkan wewenang yang dipersempit adalah
 * MEMPERTAHANKAN PEMEGANG YANG SUDAH ADA lalu mempersempit ke depan.
 * Itu yang dikerjakan di sini: siapa pun yang sebelumnya berhak
 * meninjau ditandai sebagai OHSE. Sesudah ini, penambahan peran OHSE
 * dilakukan sadar lewat Admin → Pengguna.
 *
 * Administrator tidak perlu ditandai — ia sudah lolos lewat jalur
 * tersendiri, dan menandainya akan mencampuradukkan "penjaga sistem"
 * dengan "tim keselamatan", dua hal yang sengaja dipisah.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('lms_role', 'ktt')
            ->whereNull('ohse_role')
            ->update(['ohse_role' => 'ohse']);
    }

    public function down(): void
    {
        /* Dicabut hanya dari yang memang KTT — peran OHSE yang diberikan
           tangan sesudah pemutakhiran bukan milik migrasi ini, dan
           mencabutnya akan menghapus keputusan orang lain. */
        DB::table('users')
            ->where('lms_role', 'ktt')
            ->where('ohse_role', 'ohse')
            ->update(['ohse_role' => null]);
    }
};
