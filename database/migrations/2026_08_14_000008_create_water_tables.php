<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pengelolaan air dan penirisan tambang.
 *
 * Pompanya tidak didaftarkan di sini. Pompa adalah alat, dan alat sudah
 * punya registri di modul Keselamatan Operasi lengkap dengan
 * kritikalitas serta jadwal perawatan — pompa penirisan justru termasuk
 * yang paling kritis, sebab kegagalannya menenggelamkan pit. Perintah
 * kerjanya pun otomatis terbaca oleh modul Pemeliharaan.
 *
 * Yang didaftarkan adalah kolamnya: sump, kolam pengendap, dan settling
 * pond, beserta daerah tangkapan air yang mengalir ke sana. Tanpa luas
 * tangkapan dan koefisien limpasan, hujan tidak dapat diterjemahkan
 * menjadi meter kubik, dan seluruh perkiraan luapan mustahil dihitung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('water_sumps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('kode');
            $t->string('nama');
            $t->string('jenis')->default('sump');       // sump, sediment_pond, settling_pond
            $t->string('lokasi')->nullable();

            $t->decimal('kapasitas_m3', 14, 2)->default(0);
            $t->decimal('luas_tangkapan_ha', 10, 2)->default(0);

            // Bagian hujan yang benar-benar mengalir ke kolam. Pada
            // bukaan tambang yang padat dan terbuka nilainya tinggi;
            // memakai angka hutan membuat perkiraan terlalu optimis.
            $t->decimal('koefisien_limpasan', 4, 2)->default(0.80);

            $t->decimal('elevasi_luapan_m', 8, 2)->nullable();
            $t->string('status')->default('aktif');     // aktif, arsip
            $t->date('pembersihan_terakhir')->nullable();
            $t->unsignedSmallInteger('interval_bersih_hari')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['company_id', 'status']);
            $t->index('jenis');
        });

        // Pompa yang melayani sebuah kolam. Barisnya hanya penghubung;
        // datanya sendiri tetap di registri Keselamatan Operasi.
        Schema::create('water_sump_pumps', function (Blueprint $t) {
            $t->id();
            $t->foreignId('water_sump_id')->constrained()->cascadeOnDelete();
            $t->foreignId('ko_object_id')->nullable()->constrained('ko_objects')->nullOnDelete();
            $t->string('nama')->nullable();
            $t->decimal('kapasitas_m3_jam', 12, 2)->default(0);
            $t->string('status')->default('siap');      // siap, jalan, rusak, perawatan
            $t->timestamps();

            $t->unique(['water_sump_id', 'ko_object_id']);
        });

        Schema::create('water_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('water_sump_id')->constrained()->cascadeOnDelete();

            $t->date('tanggal');
            $t->decimal('curah_hujan_mm', 8, 2)->default(0);
            $t->decimal('level_m', 8, 2)->nullable();
            $t->decimal('volume_m3', 14, 2)->default(0);
            $t->decimal('debit_masuk_m3', 14, 2)->default(0);
            $t->decimal('debit_keluar_m3', 14, 2)->default(0);
            $t->decimal('jam_pompa', 8, 2)->default(0);
            $t->decimal('energi_kwh', 12, 2)->default(0);

            // Kualitas air. Dibiarkan kosong pada hari yang tidak
            // diambil sampelnya — memaksanya terisi membuat catatan
            // harian tidak dapat disimpan pada hari biasa, dan yang
            // hilang justru datanya yang paling rutin.
            $t->decimal('ph', 4, 2)->nullable();
            $t->decimal('tss_mgl', 10, 2)->nullable();
            $t->decimal('fe_mgl', 10, 3)->nullable();
            $t->decimal('mn_mgl', 10, 3)->nullable();

            $t->string('status')->default('draf');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['water_sump_id', 'tanggal']);
            $t->index(['status', 'company_id']);
            $t->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('water_logs');
        Schema::dropIfExists('water_sump_pumps');
        Schema::dropIfExists('water_sumps');
    }
};
