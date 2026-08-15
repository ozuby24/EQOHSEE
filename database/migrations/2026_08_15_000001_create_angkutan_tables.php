<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengangkutan dan pengaturan armada (dispatch).
 *
 * Batas terhadap Mine Operations sengaja ditarik tegas, sebab keduanya
 * mencatat tonase dan mudah terbaca sebagai pekerjaan yang sama.
 * Operasi mencatat berapa yang terangkut per pit per shift — itulah
 * angka yang dilaporkan keluar. Di sini dicatat BAGAIMANA ia terangkut:
 * satu excavator beserta truk yang melayaninya, ke mana waktu satu
 * putaran habis, dan berapa lama truk berdiri antre di muka gali.
 *
 * Karena itu tonase di kedua modul tidak dijumlahkan menjadi satu.
 * Satu regu angkut adalah bagian dari tonase pit, bukan tambahannya,
 * dan menjumlahkan keduanya menghasilkan angka yang selalu terlalu
 * besar tanpa ada yang tahu dari mana kelebihannya datang.
 *
 * Penimbangan berdiri sebagai tabelnya sendiri karena kaidah muatan
 * 10/10/20 menilai tiap muatan satu per satu. Rata-rata yang disimpan
 * di baris regu tidak dapat menjawab pertanyaan "adakah satu truk yang
 * turun membawa 130% kapasitasnya" — dan justru truk itulah yang
 * menjadi kejadian, bukan rata-ratanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Armada angkut: truk dan alat muat.
        //
        // Berdiri terpisah dari registri Keselamatan Operasi karena yang
        // dibutuhkan di sini tidak ada di sana — kapasitas nominal truk
        // dan kapasitas mangkuk alat muat. Tautannya disimpan supaya
        // satu unit tetap dapat ditelusuri ke berkas kelayakannya.
        Schema::create('angkut_alats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('ko_object_id')->nullable()->constrained('ko_objects')->nullOnDelete();

            $t->string('kode');
            $t->string('nama')->nullable();
            $t->string('kelas')->default('truk');       // truk, alat-muat
            $t->string('tipe')->nullable();

            // Kapasitas nominal truk, ton. Inilah pembagi seluruh kaidah
            // muatan; tanpa angka ini kepatuhan tidak dapat dinilai sama
            // sekali, hanya tonasenya yang tercatat.
            $t->decimal('kapasitas_ton', 10, 2)->nullable();

            $t->decimal('kapasitas_bucket_m3', 8, 2)->nullable();
            $t->decimal('faktor_isi', 5, 3)->nullable();

            $t->boolean('aktif')->default(true);
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'kode']);
            $t->index(['company_id', 'kelas']);
        });

        // Satu regu angkut = satu alat muat beserta truk yang melayaninya,
        // pada satu shift dan satu rute.
        Schema::create('angkut_regus', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('alat_muat_id')->nullable()->constrained('angkut_alats')->nullOnDelete();

            $t->string('kode');
            $t->date('tanggal');
            $t->string('shift')->default('1');
            $t->string('pit')->nullable();
            $t->string('tujuan')->nullable();
            $t->string('material')->default('overburden');   // batubara, overburden, lainnya

            $t->unsignedSmallInteger('jumlah_alat_muat')->default(1);
            $t->unsignedSmallInteger('jumlah_truk');
            $t->decimal('jarak_km', 8, 3)->nullable();

            // Komponen waktu edar, menit per rit. Antre disimpan
            // terpisah dan tidak pernah dijumlahkan ke dalam waktu edar
            // yang dipakai menghitung match factor: antre adalah akibat
            // dari ketidakseimbangan, dan memasukkannya ke penyebut
            // membuat armada yang kelebihan truk terbaca seimbang.
            $t->decimal('waktu_muat_menit', 8, 2)->nullable();
            $t->decimal('waktu_angkut_menit', 8, 2)->nullable();
            $t->decimal('waktu_tumpah_menit', 8, 2)->nullable();
            $t->decimal('waktu_kembali_menit', 8, 2)->nullable();
            $t->decimal('waktu_antre_menit', 8, 2)->default(0);

            $t->unsignedInteger('ritase')->default(0);
            $t->decimal('tonase', 14, 2)->default(0);
            $t->decimal('jam_kerja', 8, 2)->default(0);
            $t->decimal('jam_delay', 8, 2)->default(0);

            // Batas kecepatan jalan angkut adalah peraturan situs, bukan
            // tetapan di dalam kode: ia berbeda antar segmen, antar
            // cuaca, dan antar perusahaan. Yang kosong tidak dinilai.
            $t->decimal('batas_kecepatan_kmh', 6, 2)->nullable();

            $t->string('status')->default('draf');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'kode']);
            $t->index(['company_id', 'status']);
            $t->index(['tanggal', 'shift']);
        });

        // Hasil timbang tiap rit.
        Schema::create('angkut_muatans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('angkut_regu_id')->constrained()->cascadeOnDelete();
            $t->foreignId('angkut_alat_id')->constrained('angkut_alats')->cascadeOnDelete();

            $t->unsignedInteger('rit_ke')->nullable();
            $t->decimal('muatan_ton', 10, 2);
            $t->dateTime('waktu_timbang')->nullable();
            $t->string('sumber')->nullable();       // jembatan timbang, payload meter
            $t->timestamps();

            $t->index(['angkut_regu_id', 'angkut_alat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('angkut_muatans');
        Schema::dropIfExists('angkut_regus');
        Schema::dropIfExists('angkut_alats');
    }
};
