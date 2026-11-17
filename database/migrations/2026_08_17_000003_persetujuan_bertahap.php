<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paraf bertahap yang terlihat, dengan satu tahap yang menentukan.
 *
 * Pengajuan kartu dan MCU melewati beberapa meja sebelum sampai ke
 * OHSE — atasan langsung, lalu kepala departemen. Orang perlu melihat
 * rantai itu: pertanyaan yang paling sering diajukan pemohon bukan
 * "sudah disetujui belum" melainkan "sekarang ada di siapa", dan tanpa
 * rantainya tergambar, jawabannya hanya dapat diperoleh dengan
 * bertanya keliling.
 *
 * TETAPI YANG MEMUTUSKAN HANYA OHSE, dan itu bukan penyederhanaan
 * melainkan keadaan yang sebenarnya. Meja-meja sebelumnya membubuhkan
 * PARAF — tanda bahwa mereka sudah melihat — bukan persetujuan.
 *
 * Perbedaan itu dijaga dua arah, dan keduanya perlu:
 *
 * 1. Paraf tidak pernah mengubah status. Ia tersimpan di tabel
 *    tersendiri, tidak menyentuh kolom alur sama sekali, sehingga tidak
 *    ada jalan bagi paraf untuk menerbitkan kartu.
 *
 * 2. Paraf yang belum ada tidak menahan OHSE. Bila ia menahan, meja
 *    sebelumnya punya kuasa memveto — persis yang menurut pemakainya
 *    tidak terjadi — dan pengajuan akan mandek di meja yang orangnya
 *    sedang cuti.
 *
 * Bahayanya ada pada penamaan, bukan pada datanya: rantai yang seluruh
 * mata rantainya hijau mudah terbaca sebagai "sudah disetujui" padahal
 * OHSE belum memutuskan apa pun. Karena itu tahap non-penentu disebut
 * "paraf", bukan "persetujuan", dan vonis akhirnya selalu diambil dari
 * kolom status — bukan dari rantainya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('persetujuan_paraf', function (Blueprint $t) {
            $t->id();

            /* Polimorfik: kartu masuk dan pengajuan MCU memakai rantai
               yang sama, dan modul berikutnya yang memerlukannya tidak
               perlu menyalin tabel ini. */
            $t->morphs('subjek');

            $t->string('tahap');                 // lihat App\Support\Tahap
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            /* Nama disalin, bukan hanya dirujuk.
               Paraf adalah catatan siapa-melihat-apa-kapan, dan catatan
               itu harus tetap terbaca setelah orangnya keluar dari
               perusahaan dan barisnya di tabel users terhapus — justru
               saat itulah ia paling sering ditanyakan. */
            $t->string('nama');
            $t->string('jabatan')->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            /* Satu tahap satu paraf. Tanpa ini, tombolnya yang tertekan
               dua kali melahirkan dua baris, dan rantainya menampilkan
               tahap yang sama dua kali. */
            $t->unique(['subjek_type', 'subjek_id', 'tahap'], 'paraf_satu_per_tahap');
        });

        Schema::table('users', function (Blueprint $t) {
            /* Peran OHSE dipisah dari lms_role dan audit_role.
               Keduanya sudah punya arti sendiri di modulnya masing-masing,
               dan menumpangkan 'ohse' pada salah satunya akan membuat
               daftar peserta pelatihan atau daftar auditor ikut berubah
               setiap kali seseorang diberi wewenang menerbitkan kartu. */
            $t->string('ohse_role')->nullable()->after('audit_role');  // ohse | null
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('ohse_role'));

        Schema::dropIfExists('persetujuan_paraf');
    }
};
