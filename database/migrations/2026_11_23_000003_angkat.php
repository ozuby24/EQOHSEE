<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lifting & Rigging Management — pengangkatan di tambang dari register
 * PAA sampai pengangkatan selesai.
 *
 * Diadaptasi dari modul LiftGuard Mining Pro (Permenaker 8/2020 tentang
 * K3 Pesawat Angkat dan Pesawat Angkut, Kepmen ESDM 1827 K/30/MEM/2018,
 * ASME B30.5/B30.9/B30.26, BS 7121) untuk kegiatan tambang: penggantian
 * komponen alat berat, crusher & CHPP, trafo, tangki BBM, man-basket.
 *
 * Enam tabel, satu alur:
 *  - angkat_alat & angkat_gear  — register pesawat angkat dan alat bantu
 *    angkat, masing-masing dengan masa berlaku riksa uji;
 *  - angkat_riksa               — laporan riksa uji (beban uji statis/
 *    dinamis, NDT, butir visual). Hanya ditambah, tidak pernah diubah:
 *    putusan TIDAK LAYAK langsung mengarantina alatnya;
 *  - angkat_rencana             — lifting plan + hasil simulasi 4 fase;
 *  - angkat_kajian              — kajian teknis pertambangan 6 pilar;
 *  - angkat_jejak               — jejak alur persetujuan rencana.
 *
 * Setiap tabel membawa company_id-nya sendiri supaya laporan riksa dan
 * kajian yang dibuka langsung lewat alamatnya tetap dibatasi perusahaan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('angkat_alat', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kode', 40);
            $t->string('nama', 160);
            $t->string('jenis', 40);                         // kunci Katalog::ALAT
            $t->string('pabrikan', 80)->nullable();
            $t->string('model', 80)->nullable();
            $t->unsignedSmallInteger('tahun')->nullable();
            $t->string('nomor_seri', 60)->nullable();
            $t->decimal('kapasitas_t', 10, 2);
            $t->decimal('berat_crane_t', 10, 2)->default(0);
            $t->decimal('boom_maks_m', 8, 2)->default(30);
            $t->decimal('berat_hook_t', 8, 2)->default(0);
            $t->string('lokasi', 150)->nullable();
            $t->string('suket_nomor', 80)->nullable();       // Surat Keterangan memenuhi syarat K3
            $t->date('suket_terbit')->nullable();
            $t->date('suket_berlaku')->nullable();
            $t->string('operator_nama', 120)->nullable();
            $t->string('operator_sio', 80)->nullable();
            $t->date('sio_berlaku')->nullable();
            $t->string('status', 20)->default('layak');      // layak, karantina
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'kode']);
            $t->index(['company_id', 'status']);
        });

        Schema::create('angkat_gear', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->string('kode', 50);                          // nomor seri / ID tag
            $t->string('nama', 160);
            $t->string('jenis', 30)->index();                // wire_rope, rantai, webbing, shackle, spreader
            $t->decimal('wll_t', 10, 2);
            $t->decimal('sf', 5, 2)->default(5);
            $t->decimal('diameter_mm', 7, 1)->nullable();
            $t->decimal('panjang_m', 7, 2)->nullable();
            $t->unsignedTinyInteger('jumlah_kaki')->default(1);
            $t->string('pabrikan', 80)->nullable();
            $t->string('lokasi_simpan', 120)->nullable();
            $t->string('kode_warna', 20)->nullable();         // warna triwulan
            $t->date('uji_terakhir')->nullable();
            $t->date('uji_berikut')->nullable();
            $t->string('status', 20)->default('layak');      // layak, karantina, afkir
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'kode']);
            $t->index(['company_id', 'status']);
        });

        Schema::create('angkat_riksa', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('alat_id')->nullable()->constrained('angkat_alat')->cascadeOnDelete();
            $t->foreignId('gear_id')->nullable()->constrained('angkat_gear')->cascadeOnDelete();
            $t->string('nomor', 60);
            $t->string('objek', 10);                         // alat, gear
            $t->string('kategori_uji', 20);                  // crane, wire_rope, rantai, webbing, shackle, spreader
            $t->string('jenis', 20)->default('berkala');     // pertama, berkala, khusus, triwulan
            $t->string('pemeriksa', 120);
            $t->string('skp_nomor', 80);
            $t->string('pjk3', 150)->nullable();
            $t->decimal('swl_t', 10, 2);
            $t->unsignedSmallInteger('uji_statis_pct')->default(0);
            $t->decimal('beban_statis_t', 10, 2)->default(0);
            $t->unsignedSmallInteger('uji_dinamis_pct')->default(0);
            $t->decimal('beban_dinamis_t', 10, 2)->default(0);
            $t->unsignedSmallInteger('tahan_menit')->default(0);
            $t->json('ukur')->nullable();                    // defleksi, keausan, elongasi, kawat putus, bukaan kait
            $t->string('ndt_metode', 20)->default('MPI');
            $t->string('ndt_hasil', 20)->default('tanpa_indikasi');
            $t->json('butir');
            $t->string('putusan', 20);                       // layak, tidak_layak
            $t->text('alasan')->nullable();
            $t->string('kode_warna', 20)->nullable();
            $t->date('diperiksa_at');
            $t->date('berlaku_sampai')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'nomor']);
            $t->index(['company_id', 'diperiksa_at']);
        });

        Schema::create('angkat_rencana', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('alat_id')->nullable()->constrained('angkat_alat')->nullOnDelete();
            $t->string('kode', 40);
            $t->string('judul', 180);
            $t->string('kategori_beban', 40);
            $t->string('klasifikasi', 20)->index();          // rutin, rekayasa, kritis
            $t->string('jenis_alat', 40);
            $t->string('lokasi', 150);
            $t->string('area', 60)->nullable();
            $t->timestamp('jadwal_at')->nullable();
            $t->json('masukan');                             // seluruh masukan simulasi
            $t->json('hasil');                               // hasil simulasi lengkap
            $t->json('gear')->nullable();                    // id alat bantu yang dipakai
            $t->decimal('berat_bersih_t', 10, 2);
            $t->decimal('berat_kotor_t', 10, 2);
            $t->decimal('radius_m', 8, 2);
            $t->decimal('boom_m', 8, 2);
            $t->decimal('kapasitas_t', 10, 2);
            $t->decimal('utilisasi_pct', 6, 1);
            $t->decimal('sudut_sling', 5, 1)->default(60);
            $t->decimal('tegangan_maks_t', 10, 2)->default(0);
            $t->decimal('tekanan_tanah', 10, 1)->default(0);
            $t->string('putusan', 20)->default('go');        // go, bersyarat, nogo
            $t->unsignedTinyInteger('skor')->default(100);
            $t->string('operator_nama', 120)->nullable();
            $t->string('operator_sio', 80)->nullable();
            $t->string('rigger_nama', 120)->nullable();
            $t->string('rigger_sertifikat', 80)->nullable();
            $t->string('signalman_nama', 120)->nullable();
            $t->string('pengawas_nama', 120)->nullable();
            $t->string('status', 20)->default('draf');       // draf, diajukan, disetujui, ditolak, selesai, batal
            $t->string('disetujui_oleh', 120)->nullable();
            $t->timestamp('disetujui_at')->nullable();
            $t->text('catatan_persetujuan')->nullable();
            $t->timestamp('selesai_at')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'kode']);
            $t->index(['company_id', 'status']);
            $t->index(['company_id', 'jadwal_at']);
        });

        Schema::create('angkat_kajian', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('rencana_id')->unique()->constrained('angkat_rencana')->cascadeOnDelete();
            $t->string('kode', 50);
            $t->json('pilar');
            $t->json('manual')->nullable();                  // jawaban butir yang dinilai orang
            $t->decimal('skor', 5, 1)->default(0);
            $t->string('putusan', 20)->default('bersyarat'); // memenuhi, bersyarat, tidak_memenuhi
            $t->text('kesimpulan')->nullable();
            $t->string('peninjau', 120)->nullable();
            $t->timestamp('ditinjau_at')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'rencana_id']);
        });

        Schema::create('angkat_jejak', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('rencana_id')->constrained('angkat_rencana')->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('aksi', 40);
            $t->string('oleh', 120)->nullable();
            $t->string('ringkas', 255);
            $t->timestamp('created_at')->useCurrent();
            $t->index(['rencana_id', 'created_at']);
            $t->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['angkat_jejak', 'angkat_kajian', 'angkat_rencana', 'angkat_riksa', 'angkat_gear', 'angkat_alat'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
