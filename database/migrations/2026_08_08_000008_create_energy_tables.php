<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Energy Performance Center.
 *
 * Catatan harian disimpan mentah — liter, kWh, meter kubik, jam operasi,
 * tonase — dan seluruh angka turunan (GJ, intensitas, tCO₂e, rupiah)
 * dihitung saat dibaca. Menyimpan hasil hitungan akan membekukan faktor
 * konversi yang dipakai saat itu, padahal faktor emisi dan harga bahan
 * bakar berubah dari tahun ke tahun.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Data induk alat
        Schema::create('energy_equipment', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kode')->unique();               // HD785-01
            $t->string('nama');
            $t->string('kategori');                     // hauling | excavator | dozer | support
            $t->string('merek')->nullable();
            $t->unsignedInteger('daya_hp')->nullable(); // engine power
            $t->decimal('payload_ton', 8, 2)->nullable();
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['kategori', 'aktif']);
        });

        // Catatan harian bahan bakar per unit
        Schema::create('energy_fuel_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('equipment_id')->constrained('energy_equipment')->cascadeOnDelete();
            $t->date('tanggal');
            $t->decimal('hm', 8, 1)->default(0);            // hour meter pada hari itu
            $t->decimal('liter', 10, 2)->default(0);
            $t->decimal('idle_jam', 6, 1)->default(0);
            $t->decimal('jarak_km', 8, 1)->default(0);
            $t->decimal('ton', 10, 2)->default(0);          // tonase yang diangkut/digali
            $t->decimal('bcm', 10, 2)->default(0);
            $t->decimal('cycle_menit', 6, 2)->nullable();
            $t->timestamps();

            $t->unique(['equipment_id', 'tanggal']);
            $t->index('tanggal');
        });

        // Catatan harian listrik per area dan sumber
        Schema::create('energy_power_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->date('tanggal');
            $t->string('area');                              // camp | workshop | ...
            $t->string('sumber');                            // pln | genset
            $t->decimal('kwh', 12, 2)->default(0);
            $t->decimal('puncak_kw', 10, 2)->default(0);
            $t->decimal('jam_operasi', 6, 1)->default(0);
            $t->decimal('liter_genset', 10, 2)->default(0);  // solar genset, bila sumbernya genset
            $t->timestamps();

            $t->unique(['tanggal', 'area', 'sumber']);
            $t->index(['area', 'sumber']);
        });

        // Sumber energi lain (gas dan sejenisnya)
        Schema::create('energy_other_logs', function (Blueprint $t) {
            $t->id();
            $t->date('tanggal');
            $t->string('jenis')->default('gas');
            $t->string('satuan')->default('m3');
            $t->decimal('jumlah', 12, 2)->default(0);
            $t->string('keterangan')->nullable();
            $t->timestamps();

            $t->index('tanggal');
        });

        // Produksi harian — pembagi seluruh angka intensitas
        Schema::create('energy_production', function (Blueprint $t) {
            $t->id();
            $t->date('tanggal')->unique();
            $t->decimal('ton', 12, 2)->default(0);
            $t->decimal('bcm', 12, 2)->default(0);
            $t->timestamps();
        });

        // Garis dasar dan sasaran intensitas energi per periode
        Schema::create('energy_baselines', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->unsignedSmallInteger('tahun');
            $t->decimal('baseline_gj_ton', 10, 6)->default(0);
            $t->decimal('target_gj_ton', 10, 6)->default(0);
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'tahun']);
        });

        // Peluang penghematan yang ditindaklanjuti
        Schema::create('energy_opportunities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('judul');
            $t->string('area')->nullable();
            $t->string('status')->default('usulan');
            $t->text('uraian')->nullable();

            // Perkiraan penghematan per bulan, disimpan pada satuan asalnya
            // agar rupiah dan karbonnya dapat dihitung ulang bila faktornya berubah.
            $t->decimal('hemat_liter', 12, 2)->default(0);
            $t->decimal('hemat_kwh', 12, 2)->default(0);

            $t->string('penanggung_jawab')->nullable();
            $t->date('target_selesai')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();

            $t->index('status');
        });

        // Rekonsiliasi bahan bakar: yang disalurkan vs yang tercatat terpakai
        Schema::create('energy_fuel_recon', function (Blueprint $t) {
            $t->id();
            $t->date('tanggal');
            $t->decimal('disalurkan_liter', 12, 2)->default(0);
            $t->decimal('stok_awal_liter', 12, 2)->default(0);
            $t->decimal('stok_akhir_liter', 12, 2)->default(0);
            $t->string('catatan')->nullable();
            $t->timestamps();

            $t->unique('tanggal');
        });
    }

    public function down(): void
    {
        foreach ([
            'energy_fuel_recon', 'energy_opportunities', 'energy_baselines',
            'energy_production', 'energy_other_logs', 'energy_power_logs',
            'energy_fuel_logs', 'energy_equipment',
        ] as $tabel) {
            Schema::dropIfExists($tabel);
        }
    }
};
