<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `paspor_induksi.hasil` boleh kosong — peserta terdaftar, belum dinilai.
 *
 * Kolomnya lahir dengan `default('Lulus')` dan NOT NULL, dan itu benar
 * selama induksi hanya dapat dicatat SESUDAH kelasnya selesai: yang
 * mengisi formulirnya sudah tahu hasilnya.
 *
 * Begitu ada pendaftaran kelas — tiga puluh nama didaftarkan hari ini
 * untuk kelas pekan depan — bentuk itu menjadi mustahil. Peserta yang
 * baru terdaftar belum punya hasil apa pun, dan satu-satunya cara
 * menyimpannya adalah menuliskan "Lulus" pada orang yang belum masuk
 * kelas. Nilai bawaan itu tidak dapat dibedakan dari hasil sungguhan
 * begitu halamannya ditutup: pada layar ia sudah lulus, pada kenyataan
 * ia belum hadir, dan tidak ada satu pun tanda yang memisahkan keduanya.
 *
 * Ini persis bentuk yang sudah dipakai `paspor_mcu.hasil` — di sana
 * kosong berarti "hasil belum kembali dari klinik", di sini kosong
 * berarti "belum dinilai". Keduanya keadaan yang sah dan harus dapat
 * disimpan apa adanya.
 *
 * Baris lama TIDAK disentuh: yang sudah berisi "Lulus" memang lulus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paspor_induksi', function (Blueprint $t) {
            $t->string('hasil')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        /* Baris tanpa hasil tidak dapat dipulangkan ke kolom NOT NULL
           tanpa mengarang nilainya. Dikosongkan menjadi "Lulus" akan
           menyatakan lulus orang yang belum dinilai — jadi barisnya
           dibuang, sebab pendaftaran tanpa hasil memang tidak punya
           tempat pada bentuk lama. */
        \DB::table('paspor_induksi')->whereNull('hasil')->delete();

        Schema::table('paspor_induksi', function (Blueprint $t) {
            $t->string('hasil')->default('Lulus')->nullable(false)->change();
        });
    }
};
