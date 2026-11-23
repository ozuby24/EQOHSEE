<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manajemen perancah (scaffolding): spesifikasi & lokasi, hitungan BoM
 * dan beban, kajian kelayakan teknis, inspeksi 16 butir, dan scafftag.
 *
 * Diadaptasi dari modul ScaffGuard Pro (OSHA 1926 Subpart L, BS EN
 * 12811-1, Permenaker 01/MEN/1980) untuk tambang dan fasilitas
 * pengolahannya: CHPP, konveyor, workshop, jetty, power house.
 *
 * Setiap tabel membawa company_id-nya sendiri. Anak-anaknya memang
 * selalu sama pemiliknya dengan unitnya, tetapi riwayat inspeksi dan
 * tag adalah catatan K3 yang dibuka langsung lewat alamatnya sendiri
 * (kartu tag, verifikasi QR) — batas perusahaannya dipasang pada baris
 * itu sendiri supaya tidak bergantung pada jalur pengikatan rute.
 *
 * Riwayat kajian, inspeksi, dan tag hanya ditambah, tidak pernah diubah:
 * tag baru menonaktifkan tag lama, bukan menimpanya. Itu yang dibaca
 * penyelidik bila suatu hari terjadi insiden.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perancah_unit', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('kode', 40);
            $t->string('judul', 180);
            $t->string('jenis', 30)->default('ringlock');        // tube_coupler, ringlock, frame, mobile, kantilever
            $t->string('kelas_beban', 20)->default('sedang');    // ringan, sedang, berat
            $t->decimal('panjang_m', 8, 2);
            $t->decimal('lebar_m', 8, 2);
            $t->decimal('tinggi_m', 8, 2);
            $t->unsignedTinyInteger('jumlah_dek')->default(1);

            // Lokasi & area kerja.
            $t->string('zona', 120)->index();
            $t->string('peralatan_ref', 80)->nullable();
            $t->string('nomor_izin', 60)->nullable();              // nomor izin kerja (PTW)
            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();
            $t->decimal('elevasi_m', 7, 2)->default(0);

            // Masukan kajian kelayakan.
            $t->string('jenis_tanah', 30)->default('beton');
            $t->unsignedSmallInteger('jumlah_ikatan')->default(0);
            $t->decimal('angin_kmh', 6, 2)->default(20);
            $t->boolean('ada_guardrail')->default(true);
            $t->boolean('ada_toeboard')->default(true);
            $t->boolean('ada_outrigger')->default(false);

            $t->string('kontraktor', 150)->nullable();
            $t->string('scaffolder_nama', 120)->nullable();
            $t->string('scaffolder_sertifikat', 80)->nullable();
            $t->date('tanggal_pasang')->nullable();
            $t->date('rencana_bongkar')->nullable();

            // Keadaan saat ini — salinan dari tag aktif, untuk peta & daftar.
            $t->string('status_tag', 20)->default('merah')->index();   // hijau, kuning, merah, dibongkar
            $t->string('kode_tag', 50)->nullable();
            $t->timestamp('inspeksi_berikut')->nullable();
            $t->timestamp('dibongkar_at')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'kode']);
        });

        Schema::create('perancah_hitung', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('unit_id')->unique()->constrained('perancah_unit')->cascadeOnDelete();
            $t->unsignedInteger('bay')->default(1);
            $t->unsignedInteger('lift')->default(1);
            $t->unsignedInteger('standard')->default(4);
            $t->unsignedInteger('ledger')->default(4);
            $t->unsignedInteger('transom')->default(4);
            $t->unsignedInteger('bracing')->default(2);
            $t->unsignedInteger('plank')->default(4);
            $t->unsignedInteger('base_plate')->default(4);
            $t->unsignedInteger('coupler')->default(16);
            $t->unsignedInteger('tangga')->default(1);
            $t->unsignedInteger('ikatan_minimum')->default(0);
            $t->decimal('luas_dek_m2', 10, 2)->default(0);
            $t->decimal('beban_mati_kg', 12, 2)->default(0);
            $t->decimal('beban_hidup_kg', 12, 2)->default(0);
            $t->decimal('beban_kaki_kn', 10, 3)->default(0);
            $t->decimal('faktor_keamanan', 8, 2)->default(0);
            $t->decimal('rasio_hb', 8, 2)->default(0);
            $t->decimal('tekanan_tanah_kn_m2', 10, 2)->default(0);
            $t->decimal('panjang_pipa_m', 10, 1)->default(0);
            $t->json('bom');
            $t->timestamps();
            $t->index(['company_id', 'unit_id']);
        });

        Schema::create('perancah_kajian', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('unit_id')->constrained('perancah_unit')->cascadeOnDelete();
            $t->string('putusan', 20);                    // layak, bersyarat, tidak_layak
            $t->unsignedTinyInteger('skor')->default(0);
            $t->boolean('perlu_pe')->default(false);       // gambar disahkan insinyur profesional
            $t->json('kriteria');
            $t->json('rekomendasi')->nullable();
            $t->string('peninjau', 120)->nullable();
            $t->timestamp('ditinjau_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'unit_id']);
        });

        Schema::create('perancah_inspeksi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('unit_id')->constrained('perancah_unit')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('jenis', 30)->default('awal');      // awal, mingguan, pasca_modifikasi, pasca_cuaca
            $t->string('inspektur', 120);
            $t->string('inspektur_sertifikat', 80);
            $t->json('hasil');
            $t->unsignedTinyInteger('lulus')->default(0);
            $t->unsignedTinyInteger('gagal_kritis')->default(0);
            $t->unsignedTinyInteger('gagal_minor')->default(0);
            $t->string('putusan', 20);                    // aman, hati_hati, bahaya
            $t->text('syarat_khusus')->nullable();
            $t->text('tindakan_perbaikan')->nullable();
            $t->timestamp('diperiksa_at');
            $t->timestamp('berlaku_sampai')->nullable();
            $t->timestamps();
            $t->index(['unit_id', 'diperiksa_at']);
            $t->index(['company_id', 'diperiksa_at']);
        });

        Schema::create('perancah_tag', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('unit_id')->constrained('perancah_unit')->cascadeOnDelete();
            $t->foreignId('inspeksi_id')->nullable()->constrained('perancah_inspeksi')->nullOnDelete();
            $t->string('kode', 50)->unique();
            $t->string('token', 40)->unique();             // untuk verifikasi QR tanpa masuk
            $t->string('warna', 10);                       // hijau, kuning, merah
            $t->string('label', 80);
            $t->unsignedSmallInteger('swl_kg_m2')->default(240);
            $t->text('syarat_khusus')->nullable();
            $t->text('alasan_tolak')->nullable();
            $t->string('terbit_oleh', 120)->nullable();
            $t->timestamp('terbit_at');
            $t->timestamp('kedaluwarsa_at')->nullable();
            $t->boolean('aktif')->default(true)->index();
            $t->timestamps();
            $t->index(['company_id', 'unit_id', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perancah_tag');
        Schema::dropIfExists('perancah_inspeksi');
        Schema::dropIfExists('perancah_kajian');
        Schema::dropIfExists('perancah_hitung');
        Schema::dropIfExists('perancah_unit');
    }
};
