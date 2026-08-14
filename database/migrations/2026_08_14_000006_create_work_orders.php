<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perintah kerja pemeliharaan.
 *
 * Alatnya tidak didaftarkan ulang di sini. Modul Keselamatan Operasi
 * sudah memiliki registri objek beserta kritikalitas dan jadwal
 * perawatannya; membuat registri kedua berarti dua daftar alat yang
 * sama-sama mengaku benar, dan yang satu pasti tertinggal — biasanya
 * yang tidak sedang dipakai orang yang menambahkan alat baru.
 *
 * Tiga stempel waktu dicatat terpisah, bukan satu lama-perbaikan:
 *
 *   dilaporkan → mulai → selesai
 *
 * Jarak dilaporkan→selesai adalah waktu alat berhenti berproduksi.
 * Jarak mulai→selesai adalah lama pengerjaannya. Selisih keduanya
 * habis menunggu — suku cadang, montir, giliran derek. Menyimpannya
 * sebagai satu angka membuat bengkel disalahkan atas gudang yang
 * kosong, dan perbaikan yang diambil menjadi salah sasaran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Registri alat milik modul Keselamatan Operasi.
            $t->foreignId('ko_object_id')->nullable()->constrained('ko_objects')->nullOnDelete();

            $t->string('nomor')->nullable();
            $t->string('jenis')->default('korektif');   // preventif, korektif, prediktif, darurat
            $t->string('prioritas')->default('sedang');
            $t->string('status')->default('dibuka');    // dibuka, dikerjakan, selesai, batal

            $t->string('gejala');
            $t->text('penyebab')->nullable();
            $t->text('tindakan')->nullable();

            $t->timestamp('dilaporkan_pada');
            $t->timestamp('mulai_pada')->nullable();
            $t->timestamp('selesai_pada')->nullable();

            // Jam meter saat gangguan; dipakai membaca umur pakai antar
            // kerusakan pada alat yang jam kerjanya tidak seragam.
            $t->decimal('hm_saat_rusak', 12, 1)->nullable();
            $t->decimal('biaya', 14, 2)->default(0);

            $t->timestamps();

            // Daftar dibuka per periode dan disaring per status; ketiga
            // kolom inilah yang dipakai menyaringnya.
            $t->index(['status', 'dilaporkan_pada']);
            $t->index(['company_id', 'status']);
            $t->index(['ko_object_id', 'dilaporkan_pada']);
            $t->index('jenis');
        });

        Schema::create('work_order_parts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('work_order_id')->constrained()->cascadeOnDelete();

            // Menunjuk barang gudang bila ada padanannya; tetap boleh
            // kosong, sebab suku cadang darurat kerap dibeli langsung
            // dan baru didaftarkan kemudian. Memaksanya terisi membuat
            // perintah kerjanya tidak dapat ditutup.
            $t->foreignId('gudang_barang_id')->nullable()->constrained('gudang_barang')->nullOnDelete();

            $t->string('nama');
            $t->decimal('jumlah', 12, 2)->default(0);
            $t->string('satuan')->nullable();
            $t->decimal('harga_satuan', 14, 2)->default(0);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_order_parts');
        Schema::dropIfExists('work_orders');
    }
};
