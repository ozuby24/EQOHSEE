<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Satu akun menunjuk paling banyak satu pekerja.
 *
 * INI SYARAT LAYANAN MANDIRI, BUKAN KERAPIAN. Seluruh halaman "milik
 * saya" menjawab satu pertanyaan lebih dahulu: pekerja mana yang
 * dimaksud akun yang sedang masuk. Tanpa keunikan, jawabannya diambil
 * dengan `first()` — dan `first()` atas enam baris yang menunjuk akun
 * yang sama memulangkan orang yang urutannya kebetulan paling awal.
 * Yang terjadi bukan galat: seorang pekerja membuka slip gajinya dan
 * membaca slip gaji rekannya, dengan nama rekannya tercetak di atasnya,
 * dan tidak ada satu pun tanda bahwa ada yang salah.
 *
 * Tautan yang berlebih DIPUTUS, bukan dibiarkan lalu ditolak indeksnya.
 * Migrasi yang gagal di tengah meninggalkan sebagian tabel sudah
 * berubah dan sisanya belum; dan yang diputus di sini dapat
 * disambungkan kembali satu per satu lewat layar pekerja, sedangkan
 * tautan yang salah tidak pernah terlihat salah.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* Sisakan tautan yang paling awal bagi tiap akun; putus sisanya.
           Yang paling awal dipilih karena ia yang paling mungkin dibuat
           bersamaan dengan akunnya. */
        $ganda = DB::table('mnr_pekerja')
            ->whereNotNull('user_id')
            ->select('user_id', DB::raw('MIN(id) as simpan'))
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($ganda as $g) {
            DB::table('mnr_pekerja')
                ->where('user_id', $g->user_id)
                ->where('id', '<>', $g->simpan)
                ->update(['user_id' => null]);
        }

        Schema::table('mnr_pekerja', function (Blueprint $t) {
            /* NULL boleh berulang — dan memang harus: sebagian besar
               pekerja tambang tidak pernah punya akun aplikasi. Yang
               dijaga hanyalah akun yang sudah terpakai. */
            $t->unique('user_id', 'mnr_pekerja_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('mnr_pekerja', function (Blueprint $t) {
            $t->dropUnique('mnr_pekerja_user_id_unique');
        });
    }
};
