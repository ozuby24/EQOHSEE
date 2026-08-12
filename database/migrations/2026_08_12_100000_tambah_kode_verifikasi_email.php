<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Verifikasi email dengan kode enam angka.
 *
 * Kodenya disimpan sebagai hash, bukan apa adanya. Kode enam angka hanya
 * punya sejuta kemungkinan; tersimpan mentah, siapa pun yang sempat
 * membaca basis data dapat memakainya sebelum kedaluwarsa.
 *
 * Percobaan ikut dicatat supaya kode dapat dikunci setelah beberapa kali
 * salah. Tanpa itu, sejuta kemungkinan dapat ditembus dengan menebak
 * berulang — dan pengaman waktu saja tidak menutupnya, sebab penebakan
 * otomatis jauh lebih cepat daripada masa berlaku kodenya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('kode_verifikasi', 100)->nullable()->after('email_verified_at');
            $t->timestamp('kode_verifikasi_at')->nullable()->after('kode_verifikasi');
            $t->unsignedTinyInteger('kode_verifikasi_percobaan')->default(0)->after('kode_verifikasi_at');
        });

        /* Akun yang sudah ada dibuat sebelum verifikasi diberlakukan.
           Membiarkannya belum terverifikasi akan mengunci seluruh pengguna
           lama dari aplikasinya sendiri pada saat pemasangan — kegagalan
           yang baru ketahuan setelah orang tidak bisa masuk. */
        DB::table('users')->whereNull('email_verified_at')
            ->update(['email_verified_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['kode_verifikasi', 'kode_verifikasi_at', 'kode_verifikasi_percobaan']);
        });
    }
};
