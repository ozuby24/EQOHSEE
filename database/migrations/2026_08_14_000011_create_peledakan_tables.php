<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengeboran dan peledakan.
 *
 * Dipisah menjadi rencana dan hasil, dan pemisahan itu bukan kerapian
 * belaka. Seluruh keputusan yang dapat mencegah kecelakaan diambil
 * SEBELUM tombolnya ditekan — isi bahan peledak per tundaan, radius
 * pengamanan, geometri lubang. Menyatukannya dengan hasil membuat
 * rancangan hanya dapat dicatat setelah peledakannya terjadi, dan pada
 * saat itu ia sudah berhenti menjadi keputusan.
 *
 * Titik terlindung berdiri sebagai tabelnya sendiri: rumah, sekolah, dan
 * bangunan yang sama dilewati banyak peledakan, dan jarak serta ambang
 * getarannya tidak berubah tiap kali. Menyalinnya ke tiap rencana berarti
 * satu perubahan izin harus disunting di puluhan tempat.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Bangunan dan tempat yang harus dilindungi dari getaran.
        Schema::create('ledak_titiks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('kode');
            $t->string('nama');
            $t->string('jenis')->default('permukiman');  // permukiman, industri, peka, infrastruktur
            $t->string('lokasi')->nullable();

            // Ambang ditetapkan izin lingkungan dan jenis bangunannya,
            // dan berganti mengikuti peraturan yang berlaku — karena itu
            // disimpan per titik, bukan ditanam di dalam kode.
            $t->decimal('ppv_ambang_mm_s', 8, 3)->nullable();
            $t->string('acuan_ambang')->nullable();

            $t->boolean('aktif')->default(true);
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'kode']);
        });

        Schema::create('ledak_rencanas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('kode');
            $t->string('lokasi')->nullable();
            $t->date('tanggal_rencana');
            $t->string('jenis_batuan')->nullable();

            // Faktor batuan Kuz-Ram: makin keras dan masif, makin besar.
            $t->decimal('faktor_batuan', 5, 2)->default(7);

            $t->decimal('diameter_lubang_mm', 8, 2);
            $t->decimal('burden_m', 8, 3);
            $t->decimal('spasi_m', 8, 3);
            $t->decimal('kedalaman_m', 8, 3);
            $t->decimal('subdrill_m', 8, 3)->nullable();
            $t->decimal('stemming_m', 8, 3)->nullable();
            $t->decimal('tinggi_jenjang_m', 8, 3);
            $t->unsignedInteger('jumlah_lubang');
            $t->string('pola')->default('selang-seling');   // selang-seling, persegi

            $t->string('bahan_peledak')->nullable();
            $t->decimal('kekuatan_relatif', 6, 2)->default(100);
            $t->decimal('isi_per_lubang_kg', 10, 3);

            // Isi per TUNDAAN, bukan isi seluruh peledakan. Inilah angka
            // yang menentukan getaran; memakai total mengubah hasilnya
            // berlipat-lipat, dan justru itulah gunanya penundaan.
            $t->decimal('isi_per_tunda_kg', 10, 3);

            $t->string('status')->default('draf');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'status']);
            $t->index('tanggal_rencana');
        });

        Schema::create('ledak_hasils', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('ledak_rencana_id')->constrained()->cascadeOnDelete();

            $t->dateTime('waktu_ledak');
            $t->decimal('volume_bcm', 14, 2)->default(0);

            // Kejadian yang menuntut tindakan segera. Bahan peledak yang
            // gagal meledak tertinggal di dalam tumpukan material, dan
            // alat gali berikutnya yang menemukannya.
            $t->boolean('ada_misfire')->default(false);
            $t->unsignedInteger('misfire_lubang')->nullable();
            $t->boolean('ada_flyrock')->default(false);
            $t->decimal('flyrock_jarak_m', 10, 2)->nullable();

            $t->decimal('backbreak_m', 8, 2)->nullable();
            $t->decimal('bongkah_persen', 5, 2)->nullable();
            $t->text('kejadian')->nullable();

            $t->string('status')->default('draf');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique('ledak_rencana_id');
            $t->index(['status', 'company_id']);
        });

        // Getaran terukur. Tiap baris satu titik pada satu peledakan;
        // dari kumpulan inilah tetapan situs dikalibrasi.
        Schema::create('ledak_ukurs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('ledak_rencana_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ledak_titik_id')->constrained()->cascadeOnDelete();

            // Jarak disimpan per pengukuran, bukan diambil dari titiknya:
            // muka peledakan berpindah tiap kali, sehingga jarak ke rumah
            // yang sama berbeda pada tiap peledakan.
            $t->decimal('jarak_m', 10, 2);
            $t->decimal('ppv_mm_s', 10, 4);
            $t->decimal('frekuensi_hz', 8, 2)->nullable();
            $t->decimal('airblast_db', 8, 2)->nullable();
            $t->string('alat_ukur')->nullable();
            $t->timestamps();

            $t->unique(['ledak_rencana_id', 'ledak_titik_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledak_ukurs');
        Schema::dropIfExists('ledak_hasils');
        Schema::dropIfExists('ledak_rencanas');
        Schema::dropIfExists('ledak_titiks');
    }
};
