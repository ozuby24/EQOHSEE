<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Respon manajemen dan bukti foto untuk temuan audit SMKP.
 *
 * Empat dari delapan formulir keluaran audit menuntut medan yang belum
 * ada sama sekali, dan ketiadaannya bukan sekadar kolom kosong:
 *
 * RESPON MANAJEMEN adalah formulir tersendiri dalam berkas audit —
 * pernyataan pihak yang diaudit atas tiap ketidaksesuaian, beserta
 * siapa yang menyatakannya dan kapan. Tanpa medannya, satu-satunya
 * tempat menuliskannya adalah kolom "tindakan", yang artinya berbeda:
 * tindakan adalah apa yang akan dikerjakan, respon adalah apakah
 * temuannya diterima. Menyatukan keduanya menghapus kemungkinan
 * manajemen MENOLAK sebuah temuan — dan penolakan itu justru yang paling
 * perlu tercatat.
 *
 * BUKTI FOTO OPEN DAN CLOSED. Temuan ditutup dengan memperlihatkan
 * keadaan sebelum dan sesudah, berdampingan. Satu kolom foto tidak
 * cukup: yang tersimpan akan menjadi salah satunya saja, dan yang
 * hilang hampir selalu yang "open" — sebab yang diunggah belakangan
 * adalah bukti perbaikan, dan ia menimpa yang lebih dulu.
 *
 * VERIFIKASI PENUTUPAN. Kolom `verifikasi` yang sudah ada hanya teks
 * bebas; siapa yang memverifikasi dan kapan tidak tercatat di mana pun.
 * Temuan yang tertutup tanpa nama pemverifikasinya tidak dapat
 * dipertanggungjawabkan kepada inspektur, dan itu persis pertanyaan
 * pertama yang diajukan saat berkas audit diperiksa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('smkp_findings', function (Blueprint $t) {
            /* Respon pihak yang diaudit. `diterima` sengaja nullable
               tiga keadaan — belum menjawab, menerima, menolak — sebab
               boolean hanya punya dua dan "belum menjawab" akan jatuh
               menjadi "menolak". */
            $t->boolean('respon_diterima')->nullable()->after('verifikasi');
            $t->text('respon_manajemen')->nullable()->after('respon_diterima');
            $t->string('respon_oleh')->nullable()->after('respon_manajemen');
            $t->date('respon_pada')->nullable()->after('respon_oleh');

            $t->string('foto_open')->nullable()->after('respon_pada');
            $t->string('foto_closed')->nullable()->after('foto_open');

            $t->string('verifikasi_oleh')->nullable()->after('foto_closed');
            $t->date('verifikasi_pada')->nullable()->after('verifikasi_oleh');
        });
    }

    public function down(): void
    {
        Schema::table('smkp_findings', function (Blueprint $t) {
            $t->dropColumn([
                'respon_diterima', 'respon_manajemen', 'respon_oleh', 'respon_pada',
                'foto_open', 'foto_closed', 'verifikasi_oleh', 'verifikasi_pada',
            ]);
        });
    }
};
