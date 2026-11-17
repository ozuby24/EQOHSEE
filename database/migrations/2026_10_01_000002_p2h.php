<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * P2H — Pemeriksaan dan Pemeliharaan Harian, sebelum unit bekerja.
 *
 * p2h_unit     unit yang wajib diperiksa tiap shift, dengan keadaannya:
 *              laik atau DITAHAN. Tertaut opsional ke objek KO supaya
 *              perintah kerja yang terbit menunjuk alat yang sama.
 * p2h_periksa  satu pemeriksaan: jawaban tiap butir disalin utuh (teks
 *              butir ikut tersimpan, jadi daftar periksa boleh berubah
 *              tanpa mengubah arti catatan lama), jumlahnya, dan hasilnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('p2h_unit', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('ko_object_id')->nullable()->constrained('ko_objects')->nullOnDelete();
            $t->string('kode', 30);
            $t->string('nama', 120);
            $t->string('jenis', 30);
            $t->string('keterangan', 150)->nullable();
            $t->decimal('hm', 10, 1)->nullable();
            $t->string('status', 12)->default('laik');
            $t->timestamp('ditahan_sejak')->nullable();
            $t->string('ditahan_karena', 500)->nullable();
            $t->foreignId('dilepas_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('dilepas_pada')->nullable();
            $t->string('catatan_lepas', 500)->nullable();
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->unique(['company_id', 'kode']);
            $t->index(['company_id', 'status']);
        });

        Schema::create('p2h_periksa', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $t->foreignId('p2h_unit_id')->constrained('p2h_unit')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('work_order_id')->nullable()->constrained('work_orders')->nullOnDelete();
            $t->string('klien_id', 40)->nullable();
            $t->string('operator', 150);
            $t->string('shift', 10);
            $t->date('tanggal');
            $t->decimal('hm', 10, 1)->nullable();
            $t->json('jawaban');
            $t->unsignedSmallInteger('jumlah_ok')->default(0);
            $t->unsignedSmallInteger('jumlah_tidak')->default(0);
            $t->unsignedSmallInteger('jumlah_na')->default(0);
            $t->string('hasil', 12);
            $t->text('catatan')->nullable();
            $t->json('foto')->nullable();
            $t->timestamps();

            $t->unique(['user_id', 'klien_id']);
            $t->index(['company_id', 'tanggal']);
            $t->index(['p2h_unit_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('p2h_periksa');
        Schema::dropIfExists('p2h_unit');
    }
};
