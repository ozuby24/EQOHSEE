<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengelolaan Lingkungan dan Reklamasi.
 *
 * Petak lahan menyimpan tahapan terjauh yang dicapainya, bukan luas per
 * tahapan. Tahapan reklamasi bertingkat — yang sudah direvegetasi pasti
 * sudah ditata dan ditebari tanah pucuk lebih dulu — sehingga menyimpan
 * luas terpisah untuk tiap tahapan mengundang penjumlahan yang
 * menghitung petak yang sama sampai tiga kali.
 *
 * Baku mutu berdiri sebagai tabelnya sendiri, bukan tetapan di dalam
 * kode. Nilai ambang lingkungan berganti mengikuti peraturan yang
 * berlaku, dan tiap izin dapat menetapkan angka yang lebih ketat
 * daripada ketentuan umumnya. Kolom `acuan` menyimpan dasar hukum tiap
 * baris, supaya laporan dapat menyebutkan angkanya berasal dari mana —
 * dan supaya penggantian satu peraturan tidak menuntut penggantian kode.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lingkungan_areas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('kode');
            $t->string('nama');
            $t->string('jenis')->default('bukaan');  // bukaan, timbunan, jalan, fasilitas, kolam
            $t->decimal('luas_ha', 12, 4)->default(0);

            $t->string('tahap')->default('belum');
            $t->date('tanggal_buka')->nullable();

            // Null selama petak masih ditambang. Jam tunggakan reklamasi
            // baru berjalan sejak penambangan di petak itu berhenti;
            // tanpa pemisahan ini, tambang yang sedang berproduksi selalu
            // tampak lalai dan angka yang selalu merah berhenti dibaca.
            $t->date('tanggal_selesai_tambang')->nullable();
            $t->date('rencana_selesai_reklamasi')->nullable();

            $t->unsignedInteger('pohon_rencana')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'tahap']);
            $t->index('jenis');
        });

        Schema::create('reklamasi_kemajuans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('lingkungan_area_id')->constrained()->cascadeOnDelete();

            $t->date('tanggal');
            $t->string('tahap');                       // tahapan yang dicapai pada tanggal ini
            $t->decimal('luas_ha', 12, 4)->default(0); // luas yang mencapai tahapan itu
            $t->unsignedInteger('pohon_ditanam')->nullable();
            $t->decimal('tingkat_tumbuh_persen', 5, 2)->nullable();

            $t->string('status')->default('draf');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['status', 'company_id']);
            $t->index(['lingkungan_area_id', 'tanggal']);
        });

        Schema::create('lingkungan_parameters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('kode');
            $t->string('nama');
            $t->string('media')->default('air');   // air, udara, kebisingan, tanah
            $t->string('satuan')->nullable();

            // Keduanya boleh kosong. pH punya batas atas dan bawah,
            // debu hanya batas atas, dan oksigen terlarut hanya batas
            // bawah — memaksa keduanya terisi memalsukan salah satunya.
            $t->decimal('batas_min', 14, 4)->nullable();
            $t->decimal('batas_maks', 14, 4)->nullable();

            $t->string('acuan')->nullable();       // dasar hukum baris ini
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->unique(['company_id', 'kode']);
            $t->index('media');
        });

        Schema::create('lingkungan_pantaus', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('lingkungan_parameter_id')->constrained()->cascadeOnDelete();

            $t->string('titik');                   // titik penaatan
            $t->date('tanggal');
            $t->decimal('nilai', 14, 4);
            $t->string('laboratorium')->nullable();

            $t->string('status')->default('draf');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['status', 'company_id']);
            $t->index(['tanggal', 'titik']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lingkungan_pantaus');
        Schema::dropIfExists('lingkungan_parameters');
        Schema::dropIfExists('reklamasi_kemajuans');
        Schema::dropIfExists('lingkungan_areas');
    }
};
