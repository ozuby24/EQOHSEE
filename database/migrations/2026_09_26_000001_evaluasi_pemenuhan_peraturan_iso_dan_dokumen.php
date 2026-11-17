<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Identifikasi dan evaluasi pemenuhan — satu mesin, tiga sumber kewajiban.
 *
 * Auditor menanyakan hal yang sama untuk ketiganya: kewajiban apa yang
 * mengikat kita, sudah dipenuhi atau belum, dan kalau belum siapa yang
 * mengerjakan sampai kapan. Yang berbeda hanya dari mana kewajiban itu
 * datang — pasal peraturan perundangan, klausul standar ISO, atau
 * persyaratan dokumen terkendali.
 *
 * Karena itu tabelnya satu, bukan tiga. Tiga salinan berarti tiga
 * tempat memperbaiki perhitungan persentase, tiga dasbor yang perlahan
 * berbeda angkanya, dan tiga ekspor yang bentuknya tidak lagi sama
 * sesudah setahun.
 *
 * ── "Belum dinilai" TIDAK sama dengan "N/A" ──
 *
 * Keduanya berbeda arti sejauh-jauhnya: N/A adalah KEPUTUSAN penilai
 * bahwa pasal itu tidak mengikat kegiatan perusahaan, sedangkan belum
 * dinilai adalah pekerjaan yang belum dikerjakan. Menyatukannya —
 * memberi butir baru status N/A sebagai nilai awal — membuat register
 * yang belum disentuh sama sekali terbaca sebagai register yang sudah
 * selesai dievaluasi dan kebetulan tidak ada satu pun yang berlaku.
 * Karena itu `status` boleh NULL, dan NULL berarti belum dinilai.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* 1) Subjek yang dinilai: satu peraturan, satu standar, atau
              satu dokumen terkendali — beserta identitas yang dicetak
              di kepala tabel evaluasinya. */
        Schema::create('compliance_subjects', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Peraturan | ISO | Dokumen
            $t->string('sumber')->default('Peraturan');

            // Kode register per aspek, mis. S1, H2, E3 — dipakai di daftar
            $t->string('kode')->nullable();

            $t->string('jenis')->nullable();          // Undang-Undang, Peraturan Menteri, Standar, …
            $t->string('nomor');                       // "Undang-Undang Nomor 1 Tahun 1970"
            $t->string('judul');
            $t->date('tanggal_terbit')->nullable();
            $t->string('instansi')->nullable();        // penerbit

            /* Aspek memakai kunci pilar EQOHSEE (safety, environment,
               occhealth, …) supaya warna, nama, dan penempatannya tidak
               ditetapkan dua kali. NULL berarti lintas aspek — "Umum". */
            $t->string('aspek')->nullable();

            $t->text('ruang_lingkup')->nullable();
            $t->text('rangkuman')->nullable();

            $t->integer('tahun');                      // tahun evaluasi
            $t->string('status')->default('Tetap');    // Draf | Tetap

            /* Hasil rangkuman otomatis ditandai, bukan disamarkan.
               Rangkuman mesin salah dengan cara yang meyakinkan, dan
               yang membacanya berhak tahu bahwa yang dibacanya belum
               diperiksa manusia. */
            $t->boolean('dari_ai')->default(false);

            // Sambungan ke sumbernya, bila bukan peraturan lepas
            $t->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $t->string('iso_kode')->nullable();        // mis. "14001"

            $t->json('berkas')->nullable();            // salinan peraturannya
            $t->timestamps();

            $t->index(['company_id', 'tahun', 'sumber']);
            $t->index('aspek');
        });

        /* 2) Butir kewajiban: satu pasal/ayat, satu klausul, atau satu
              persyaratan dokumen. Di sinilah penilaian sesungguhnya. */
        Schema::create('compliance_points', function (Blueprint $t) {
            $t->id();
            $t->foreignId('subject_id')->constrained('compliance_subjects')->cascadeOnDelete();

            $t->string('penunjuk');                    // "Pasal 3 Ayat (1)" | "6.1.2" | "Distribusi terkendali"
            $t->text('rangkuman')->nullable();         // kewajiban yang diatur
            $t->text('penerapan')->nullable();         // yang sudah dikerjakan perusahaan

            // Comply | Not Comply | N/A — NULL berarti belum dinilai
            $t->string('status')->nullable();

            $t->text('keterangan')->nullable();        // bukti, nomor dokumen, catatan
            $t->text('tindak_lanjut')->nullable();     // hanya terisi saat Not Comply
            $t->string('pic')->nullable();
            $t->date('target')->nullable();

            $t->integer('order_index')->default(1);
            $t->timestamps();

            $t->index(['subject_id', 'order_index']);
            $t->index('status');
        });

        /* 3) Rekap bulanan (PICA): potret angka pada akhir bulan,
              beserta evaluasi dan rencana tindak lanjutnya.

              Disimpan sebagai potret, bukan dihitung ulang dari butir
              saat dibaca. Butirnya berubah sepanjang tahun — dinilai
              ulang, ditambah, dinaikkan statusnya — dan rekap Januari
              yang dihitung ulang pada bulan Desember memulangkan angka
              Desember. Yang ditandatangani pada rapat Januari adalah
              angka Januari. */
        Schema::create('compliance_recaps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('sumber')->default('Peraturan');
            $t->integer('tahun');
            $t->unsignedTinyInteger('bulan');          // 1..12

            $t->unsignedInteger('comply')->default(0);
            $t->unsignedInteger('not_comply')->default(0);
            $t->unsignedInteger('na')->default(0);
            $t->unsignedInteger('belum')->default(0);

            $t->text('evaluasi')->nullable();
            $t->text('rencana')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'sumber', 'tahun', 'bulan'], 'rekap_kepatuhan_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compliance_recaps');
        Schema::dropIfExists('compliance_points');
        Schema::dropIfExists('compliance_subjects');
    }
};
