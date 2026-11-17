<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengumuman yang dapat dibaca utuh tanpa meninggalkan halaman.
 *
 * Sampai sekarang satu baris berita hanya punya judul, isi, dan tanggal.
 * Isinya teks polos, jadi pengumuman yang sesungguhnya — rambu baru di
 * simpang hauling, denah titik kumpul yang berubah, surat edaran ber-kop
 * — tidak punya tempat untuk gambar maupun lampirannya. Yang terjadi di
 * lapangan: gambarnya beredar lewat WhatsApp dan pengumuman resminya
 * dibaca sebagai paragraf yang menyebut gambar yang tidak ada di situ.
 *
 * ── Kenapa hanya SATU sampul dan SATU lampiran ──
 *
 * Bukan karena lebih mudah. Kolom jamak berupa JSON akan menampung
 * berapa pun, dan justru itu masalahnya: bundel dokumen akan diunggah
 * ke satu pengumuman, tempat yang tidak akan dicari siapa pun ketika
 * dokumennya dibutuhkan lagi — persis alasan batas bukti audit
 * diperketat. Satu sampul dan satu lampiran memaksa penulisnya memilih
 * mana yang benar-benar harus dibaca; yang selebihnya adalah dokumen,
 * dan dokumen punya modulnya sendiri.
 *
 * Keduanya disimpan di disk TERTUTUP dan disajikan lewat rute berjaga.
 * Pengumuman melekat pada perusahaan dan hanya tampil di halaman yang
 * menuntut login — menutupnya tidak menghilangkan apa pun, sedangkan
 * membiarkannya terbuka berarti surat edaran satu perusahaan terbaca
 * lewat /storage/… oleh siapa saja yang menebak jalurnya.
 *
 * ── Kenapa `news_reads` ADA tabelnya sendiri ──
 *
 * Pengumuman keselamatan bukan kabar; ia kewajiban. Yang ditanyakan
 * pengawas sesudah menerbitkannya selalu pertanyaan yang sama — siapa
 * yang sudah membacanya — dan pertanyaan itu tidak dapat dijawab oleh
 * penghitung angka pada barisnya sendiri. Angka menjawab "berapa",
 * bukan "siapa", dan yang diperlukan saat audit adalah yang kedua.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news', function (Blueprint $t) {
            /* Ringkasan yang DITULIS penulisnya, bukan potongan otomatis.
               Daftar berita sudah memotong isi dengan Str::limit, dan
               hasilnya kerap terputus di tengah angka atau nama lokasi.
               Untuk pengumuman, kalimat pertama yang salah potong adalah
               kalimat yang paling dibaca. Boleh kosong — yang tidak
               mengisinya tetap memperoleh potongan otomatis seperti
               sebelumnya. */
            $t->string('excerpt', 300)->nullable()->after('title');

            $t->string('cover')->nullable()->after('content');

            $t->string('lampiran')->nullable()->after('cover');

            /* Nama aslinya disimpan terpisah. Laravel mengganti nama
               berkas yang diunggah dengan rangkaian acak, dan lampiran
               yang muncul sebagai "9Xk2mP1q.pdf" pada surat edaran tidak
               memberi tahu siapa pun apa isinya sebelum diunduh. */
            $t->string('lampiran_nama', 200)->nullable()->after('lampiran');
        });

        Schema::create('news_reads', function (Blueprint $t) {
            $t->id();
            $t->foreignId('news_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->timestamps();

            /* Unik berpasangan: membaca ulang pengumuman yang sama tidak
               boleh menambah satu baris lagi. Tanpa ini, "sudah dibaca 40
               orang" pada pengumuman yang dibuka empat kali oleh sepuluh
               orang terbaca sebagai seluruh regu — dan itu angka yang
               dipakai memutuskan apakah briefing perlu diulang. */
            $t->unique(['news_id', 'user_id'], 'news_reads_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_reads');

        Schema::table('news', function (Blueprint $t) {
            $t->dropColumn(['excerpt', 'cover', 'lampiran', 'lampiran_nama']);
        });
    }
};
