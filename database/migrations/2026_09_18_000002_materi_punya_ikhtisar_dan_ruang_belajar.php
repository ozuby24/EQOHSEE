<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materi berhenti menjadi sebaris tautan.
 *
 * Sampai sekarang satu materi hanya judul, jenis, dan satu URL, dan
 * seluruhnya tergambar sebagai satu baris di dalam daftar modul. Tidak
 * ada halaman miliknya sendiri, tidak ada cara mengetahui isinya sebelum
 * membukanya, dan tidak ada tanda sudah selesai atau belum.
 *
 * Akibatnya yang paling terasa bukan soal rupa: peserta yang membuka
 * kursus melihat dua puluh tautan yang seluruhnya tampak sama, tanpa
 * satu pun petunjuk mana yang lima menit dan mana yang satu jam, mana
 * yang menuntut SOP dibaca lebih dulu, dan sudah sampai mana dirinya.
 *
 * ── Tiga kolom pada materi ──
 *
 * `outcomes`      apa yang akan dikuasai sesudahnya. Ditulis sebagai
 *                 daftar, bukan paragraf: yang dibaca orang sebelum
 *                 memutuskan membuka sesuatu adalah butir, bukan prosa.
 * `prerequisite`  apa yang harus dibaca atau dikerjakan lebih dulu.
 *                 Pada pelatihan K3 ini bukan hiasan — materi praktik
 *                 yang dibuka sebelum SOP-nya dibaca adalah urutan yang
 *                 justru hendak dicegah pelatihannya.
 * `duration_minutes` berapa lama. Satu-satunya angka yang ditanyakan
 *                 orang sebelum memutuskan mengerjakannya sekarang atau
 *                 sesudah shift.
 *
 * Ketiganya BOLEH KOSONG. Materi yang sudah ada tidak tiba-tiba menjadi
 * cacat karena kolom baru; halamannya menggambar bagian yang terisi saja
 * dan melewatkan sisanya, tanpa kotak kosong berlabel "belum diisi".
 *
 * ── material_completions, di samping module_completions ──
 *
 * Progres kursus TETAP dihitung dari modul, bukan dari materi. Yang
 * berubah hanya cara modul menjadi selesai: menuntaskan seluruh
 * materinya menandai modulnya sendiri. Tombol "tandai selesai" pada
 * modul tetap ada dan tetap berlaku — modul tanpa materi sama sekali
 * tidak punya jalan lain.
 *
 * Dipisah begitu supaya sertifikat, rekap, dan seluruh laporan yang
 * sudah ada membaca angka yang sama persis seperti sebelum migrasi ini.
 * Menghitung ulang progres dari materi akan mengubah angka pada
 * sertifikat yang sudah terbit — tanpa satu pun galat, dan tanpa satu
 * pun cara menjelaskannya kepada yang memegangnya.
 *
 * ── Lampiran sebagai tabel, bukan kolom JSON ──
 *
 * Satu materi kerap menyeret SOP, lembar periksa, dan berkas latihannya
 * sendiri. Ditaruh sebagai JSON pada barisnya, urutannya tidak dapat
 * diubah tanpa menulis ulang seluruh larik, dan menghapus satu lampiran
 * berarti mengirim balik yang lain — dengan risiko yang sudah dikenal:
 * dua orang menyunting satu materi, dan yang menyimpan belakangan
 * menghapus lampiran yang baru ditambahkan yang lain.
 *
 * ── Tanya jawab yang melekat pada materinya ──
 *
 * Pertanyaan tentang satu materi ditanyakan SAAT membukanya, bukan di
 * tempat lain. Yang tidak punya tempat di situ akan ditanyakan lewat
 * pesan pribadi kepada trainer — dijawab sekali, kepada satu orang, dan
 * hilang. Pertanyaan berikutnya yang sama persis dimulai dari nol.
 *
 * `parent_id` satu tingkat saja. Percakapan berlapis-lapis pada materi
 * pelatihan berubah menjadi utas yang tidak terbaca, dan yang dicari
 * orang di sini adalah jawaban, bukan diskusi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $t) {
            $t->json('outcomes')->nullable()->after('description');
            $t->text('prerequisite')->nullable()->after('outcomes');
            $t->unsignedSmallInteger('duration_minutes')->nullable()->after('prerequisite');
        });

        Schema::create('material_completions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('material_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'material_id'], 'material_completions_unik');
        });

        Schema::create('material_attachments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('material_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->string('url', 1000);
            $t->integer('order_index')->default(1);
            $t->timestamps();
        });

        Schema::create('material_discussions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('material_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            /* Jawaban menunjuk pertanyaannya. cascadeOnDelete: pertanyaan
               yang dihapus membawa serta jawabannya — jawaban tanpa
               pertanyaan adalah kalimat menggantung yang tidak dapat
               dipahami siapa pun yang membacanya kemudian. */
            $t->foreignId('parent_id')->nullable()
              ->constrained('material_discussions')->cascadeOnDelete();

            $t->text('body');
            $t->timestamps();

            $t->index(['material_id', 'parent_id'], 'material_discussions_utas_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('material_discussions');
        Schema::dropIfExists('material_attachments');
        Schema::dropIfExists('material_completions');

        Schema::table('materials', function (Blueprint $t) {
            $t->dropColumn(['outcomes', 'prerequisite', 'duration_minutes']);
        });
    }
};
