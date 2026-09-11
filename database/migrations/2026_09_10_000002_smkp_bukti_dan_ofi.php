<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua tambahan pada audit SMKP: berkas bukti per butir, dan peluang
 * perbaikan (OFI) bagi butir yang sudah sempurna.
 *
 * MENGAPA BUKTI PINDAH KE TABELNYA SENDIRI.
 *
 * Kolom `bukti` yang sudah ada pada `smkp_audits.hasil` berupa teks 500
 * aksara — auditor menuliskan "SOP-HSE-012 rev.3, wawancara Kabag
 * Produksi". Itu tetap dipertahankan dan tetap berguna: ia jawaban atas
 * "bukti apa", bukan "mana buktinya".
 *
 * Yang tidak dapat dijawab teks adalah pertanyaan kedua, dan itulah yang
 * ditanyakan Inspektur Tambang saat berkas audit diminta. Menyimpannya
 * sebagai jalur berkas di dalam JSON yang sama akan membuat satu kolom
 * memikul dua arti sekaligus — dan penghapusan sebuah butir akan
 * meninggalkan berkasnya di disk tanpa satu pun baris yang menyebutnya.
 *
 * MENGAPA OFI BUKAN TEMUAN.
 *
 * `smkp_findings` mencatat KETIDAKSESUAIAN: mayor, minor, dan
 * observasi — semuanya menuntut tindakan perbaikan dengan penanggung
 * jawab dan tenggat, dan semuanya menurunkan nilai. OFI kebalikannya:
 * ia hanya boleh lahir dari butir yang capaiannya PENUH, tidak
 * menurunkan nilai apa pun, dan tidak wajib ditindaklanjuti.
 *
 * Menumpangkannya pada tabel temuan berarti setiap rekap
 * ketidaksesuaian harus menyaringnya lebih dahulu — dan rekap yang lupa
 * menyaring melaporkan perusahaan yang sudah sempurna sebagai punya
 * belasan temuan. Kegagalannya diam: angkanya naik, tidak ada galat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('smkp_bukti', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained('smkp_audits')->cascadeOnDelete();

            /* Kode butir kriteria, mis. "II.2.1". Sengaja TANPA kunci
               asing: butir kriteria hidup di berkas acuan
               resources/data/smkp/elemen.json, bukan di basis data —
               ia salinan lampiran Kepdirjen yang berlaku sama bagi
               setiap perusahaan. */
            $t->string('kode', 20)->index();

            $t->string('file_path', 500);
            $t->string('file_name', 255);
            $t->unsignedBigInteger('file_size')->default(0);
            $t->string('mime', 120)->nullable();

            /* Keterangan singkat: nomor dokumen, tanggal terbit, siapa
               yang menunjukkannya. Berkas tanpa keterangan menuntut
               pembacanya membukanya satu per satu untuk tahu isinya. */
            $t->string('catatan', 300)->nullable();

            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['audit_id', 'kode']);
        });

        Schema::create('smkp_ofi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('audit_id')->constrained('smkp_audits')->cascadeOnDelete();

            /* Kode sub-elemen ATAU sub-sub-elemen. Keduanya sah, dan
               pemisahannya disimpan supaya lembar OFI dapat menyebut
               "peluang pada sub-elemen V.5" berbeda dari "peluang pada
               rincian V.5.2" — dua hal yang lingkupnya berbeda. */
            $t->string('kode', 20)->index();
            $t->string('lingkup', 10)->default('sub');   // sub | subsub

            $t->text('uraian');
            $t->text('saran')->nullable();

            $t->string('penanggung_jawab', 150)->nullable();
            $t->date('target')->nullable();

            /* terbuka | ditindaklanjuti | ditutup. Bukan wajib — OFI
               yang tidak dikerjakan bukan pelanggaran. */
            $t->string('status', 20)->default('terbuka')->index();

            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            /* Satu peluang per butir per audit. Dua baris pada kode yang
               sama membuat lembar OFI menyebut butir yang sama dua kali
               dengan saran yang berselisih, dan yang membacanya tidak
               punya cara memilih. */
            $t->unique(['audit_id', 'kode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('smkp_ofi');
        Schema::dropIfExists('smkp_bukti');
    }
};
