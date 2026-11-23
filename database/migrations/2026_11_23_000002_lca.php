<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Life Cycle Assessment (ISO 14040/14044, ISO 14067).
 *
 * Diadaptasi dari modul EcoTrace LCA untuk produk tambang — batubara
 * FOB tongkang, bijih nikel, doré emas, batu gamping — dengan lima tahap
 * daur hidup yang disebut menurut kegiatan tambang: ekstraksi, angkutan,
 * pengolahan & energi, pemuatan & distribusi, akhir hidup & reklamasi.
 *
 * Faktor dampak: baris ber-company_id NULL adalah PUSTAKA BERSAMA yang
 * dipasang `lca:pasang` dan dipakai seluruh perusahaan; perusahaan dapat
 * menambah faktornya sendiri. Setiap baris inventori menyimpan SALINAN
 * faktornya saat dihitung, sehingga studi yang sudah diverifikasi tidak
 * berubah angkanya ketika pustaka diperbarui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lca_faktor', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('kode', 60);
            $t->string('nama', 180);
            $t->string('kategori', 30)->index();           // energi, material, peledak, transportasi, kemasan, air, limbah, lahan
            $t->string('tahap_bawaan', 20)->default('olah');
            $t->unsignedTinyInteger('scope')->default(3);
            $t->string('satuan', 20);
            $t->decimal('gwp', 20, 9)->default(0);          // kg CO2e / satuan
            $t->decimal('ap', 20, 9)->default(0);           // kg SO2e
            $t->decimal('ep', 20, 9)->default(0);           // kg PO4e
            $t->decimal('wsf', 20, 9)->default(0);          // m3 world eq
            $t->decimal('ced', 20, 9)->default(0);          // MJ
            $t->decimal('odp', 20, 12)->default(0);         // kg CFC-11e
            $t->string('sumber', 160);
            $t->unsignedSmallInteger('tahun')->default(2024);
            $t->string('wilayah', 60)->default('Indonesia');
            $t->boolean('perlu_cocok')->default(true);
            $t->boolean('aktif')->default(true);
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'kode']);
        });

        Schema::create('lca_studi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('induk_id')->nullable()->constrained('lca_studi')->nullOnDelete();
            $t->string('kode', 40);
            $t->string('versi', 10)->default('1.0');
            $t->string('judul', 180);
            $t->string('produk', 150);
            $t->string('sku', 80)->nullable();
            $t->string('fasilitas', 150);
            $t->string('wilayah', 40)->default('ID-KAL');
            $t->decimal('fu_nilai', 18, 6)->default(1);
            $t->string('fu_label', 100);
            $t->decimal('aliran_ref_kg', 18, 6)->default(1000);
            $t->string('batas_sistem', 30)->default('cradle_to_gate');
            $t->string('metode', 40)->default('IPCC_2021_GWP100');
            $t->string('alokasi', 30)->default('massa');
            $t->decimal('cutoff_pct', 5, 2)->default(1);
            $t->date('periode_mulai');
            $t->date('periode_selesai');
            $t->decimal('volume_tahunan', 18, 2)->default(0);   // unit fungsional per tahun
            $t->decimal('baseline_gwp', 18, 6)->nullable();     // kg CO2e per FU, tahun dasar
            $t->decimal('target_gwp', 18, 6)->nullable();       // kg CO2e per FU, target
            foreach (['gwp', 'ap', 'ep', 'wsf', 'ced'] as $k) $t->decimal('total_'.$k, 20, 6)->default(0);
            $t->decimal('total_odp', 20, 12)->default(0);
            $t->decimal('dqr', 4, 2)->nullable();
            $t->string('status', 20)->default('draf')->index();  // draf, tinjau, terverifikasi, terbit, arsip
            $t->text('tujuan')->nullable();
            $t->string('audiens', 150)->nullable();
            $t->string('pemverifikasi', 120)->nullable();
            $t->timestamp('diverifikasi_at')->nullable();
            $t->text('catatan_tinjau')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'kode', 'versi']);
        });

        Schema::create('lca_inventori', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('studi_id')->constrained('lca_studi')->cascadeOnDelete();
            $t->foreignId('faktor_id')->constrained('lca_faktor')->restrictOnDelete();
            $t->string('tahap', 20);
            $t->string('nama', 180);
            $t->decimal('jumlah', 20, 6);
            $t->string('satuan', 20);
            $t->decimal('jumlah_normal', 20, 6);
            $t->decimal('jarak_km', 12, 2)->nullable();
            $t->decimal('daur_ulang_pct', 5, 2)->default(0);
            $t->decimal('alokasi_pct', 5, 2)->default(100);
            foreach (['gwp', 'ap', 'ep', 'wsf', 'ced'] as $k) {
                $t->decimal('f_'.$k, 20, 9)->default(0);
                $t->decimal($k, 20, 6)->default(0);
            }
            $t->decimal('f_odp', 20, 12)->default(0);
            $t->decimal('odp', 20, 12)->default(0);
            foreach (['p_reliabilitas', 'p_kelengkapan', 'p_temporal', 'p_geografis', 'p_teknologi'] as $k) {
                $t->unsignedTinyInteger($k)->default(2);
            }
            $t->decimal('dqr', 4, 2)->default(2);
            $t->decimal('ketidakpastian', 5, 2)->default(13.5);
            $t->string('sumber_data', 20)->default('primer');   // primer, sekunder, energi
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->index(['studi_id', 'tahap']);
            $t->index(['company_id', 'studi_id']);
        });

        Schema::create('lca_hasil', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('studi_id')->constrained('lca_studi')->cascadeOnDelete();
            $t->string('kategori', 10);
            $t->string('tahap', 20);
            $t->decimal('nilai', 20, 9)->default(0);
            $t->string('satuan', 30);
            $t->decimal('porsi', 7, 2)->default(0);
            $t->decimal('normal', 20, 12)->default(0);
            $t->timestamps();
            $t->unique(['studi_id', 'kategori', 'tahap']);
            $t->index(['company_id', 'studi_id']);
        });

        Schema::create('lca_skenario', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('studi_id')->constrained('lca_studi')->cascadeOnDelete();
            $t->string('nama', 150);
            $t->text('deskripsi')->nullable();
            foreach (['ebt', 'bbm', 'logistik', 'daur_ulang', 'limbah'] as $k) $t->decimal('tuas_'.$k, 5, 2)->default(0);
            $t->decimal('baseline', 20, 6)->default(0);
            $t->decimal('proyeksi', 20, 6)->default(0);
            $t->decimal('reduksi_pct', 6, 2)->default(0);
            $t->decimal('hemat_t_tahun', 18, 2)->default(0);
            $t->decimal('hemat_idr', 20, 2)->default(0);
            $t->decimal('hemat_eur', 18, 2)->default(0);
            $t->json('rincian')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'studi_id']);
        });

        Schema::create('lca_jejak', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('studi_id')->nullable()->constrained('lca_studi')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('aksi', 60);
            $t->string('ringkas', 255);
            $t->json('lama')->nullable();
            $t->json('baru')->nullable();
            $t->string('ip', 45)->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['studi_id', 'created_at']);
            $t->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['lca_jejak', 'lca_skenario', 'lca_hasil', 'lca_inventori', 'lca_studi', 'lca_faktor'] as $t) Schema::dropIfExists($t);
    }
};
