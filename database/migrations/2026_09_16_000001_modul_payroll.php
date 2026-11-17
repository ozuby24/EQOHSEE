<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penggajian, PPh 21 TER, dan BPJS.
 *
 * ACUAN PAJAK DISIMPAN SEBAGAI DATA YANG DAPAT DIVERIFIKASI, bukan
 * sebagai angka di dalam kode. Tabel tarif efektif PMK 168/2023 berisi
 * seratus dua puluhan baris; salah satu batas saja sudah cukup untuk
 * memotong pajak seseorang dengan tarif yang keliru selama sebelas
 * bulan — dan yang menanggung dendanya perusahaan.
 *
 * Karena itu `pay_acuan` memuat penanda TERVERIFIKASI, dan periode
 * gaji TIDAK DAPAT DIKUNCI selama masih ada acuan yang belum
 * diverifikasi seseorang terhadap naskah peraturannya. Perhitungan
 * tetap boleh dijalankan dan dilihat — yang ditahan adalah
 * penguncian, yaitu saat angkanya berhenti menjadi pratinjau dan
 * mulai menjadi dasar pembayaran.
 *
 * PERIODE YANG TERKUNCI TIDAK DAPAT DIHITUNG ULANG. Sesudah slip
 * dibagikan dan uangnya ditransfer, menghitung ulang berarti angka di
 * layar berbeda dari angka di rekening pekerja — dan yang bertanya
 * "kenapa beda" tidak akan pernah mendapat jawaban yang dapat
 * ditunjukkan.
 *
 * ACUAN:
 *   · PP 58/2023 & PMK 168/2023 — PPh 21 TER kategori A/B/C.
 *   · UU 7/2021 (HPP) pasal 17 — tarif progresif rekonsiliasi Desember.
 *   · PMK 101/2016 — PTKP.
 *   · Perpres 63/2022 & 59/2024 — BPJS Kesehatan.
 *   · PP 6/2025 & ketentuan BPJS Ketenagakerjaan 2026.
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * Acuan peraturan, beserta penanda sudah diverifikasi atau
         * belum.
         *
         * Tanpa penanda ini, tabel tarif yang diketik dari ingatan
         * tidak dapat dibedakan dari tabel yang sudah dicocokkan
         * dengan naskah peraturannya — dan keduanya sama-sama
         * menghasilkan angka yang tampak meyakinkan.
         */
        Schema::create('pay_acuan', function (Blueprint $t) {
            $t->id();

            $t->string('kunci', 30)->unique();
            $t->string('nama', 120);
            $t->string('sumber', 200);
            $t->text('catatan')->nullable();

            $t->boolean('terverifikasi')->default(false);
            $t->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diverifikasi_pada')->nullable();

            $t->timestamps();
        });

        /**
         * Tarif efektif rata-rata bulanan — PMK 168/2023 lampiran.
         *
         * Disimpan sebagai baris, bukan sebagai larik di dalam kode:
         * tarifnya berubah lewat peraturan menteri, dan perubahan
         * lewat peraturan tidak seharusnya menuntut penerapan ulang
         * aplikasi.
         */
        Schema::create('pay_ter', function (Blueprint $t) {
            $t->id();

            /* A | B | C — ditentukan status PTKP. */
            $t->string('kategori', 1);

            $t->decimal('batas_bawah', 16, 2)->default(0);

            /* NULL berarti tidak berbatas atas — lapis terakhir. */
            $t->decimal('batas_atas', 16, 2)->nullable();

            /* Tarif dalam PERSEN, bukan pecahan: lampirannya menulis
               "0,25%" dan "34%", dan menyimpannya sebagai 0,0025
               membuat tiap pembacaan menuntut penerjemahan yang dapat
               salah. */
            $t->decimal('tarif', 6, 4);

            $t->timestamps();

            $t->index(['kategori', 'batas_bawah']);
        });

        /**
         * Satu periode gaji per perusahaan per bulan.
         *
         * Dipisah per perusahaan, bukan satu run untuk semuanya: tiap
         * entitas punya NPWP sendiri, e-Bupot sendiri, dan kas sendiri.
         */
        Schema::create('pay_periode', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->unsignedSmallInteger('tahun');
            $t->unsignedTinyInteger('bulan');

            /* draft | terhitung | terkunci */
            $t->string('status', 12)->default('draft');

            $t->timestamp('dihitung_pada')->nullable();
            $t->foreignId('dihitung_oleh')->nullable()->constrained('users')->nullOnDelete();

            $t->timestamp('dikunci_pada')->nullable();
            $t->foreignId('dikunci_oleh')->nullable()->constrained('users')->nullOnDelete();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'tahun', 'bulan']);
            $t->index(['company_id', 'status']);
        });

        /**
         * Slip gaji satu orang pada satu periode.
         *
         * SELURUH KOMPONENNYA DISIMPAN TERPISAH, bukan hanya jumlah
         * akhirnya. Slip gaji adalah dokumen yang dipersengketakan, dan
         * sengketanya selalu tentang satu komponen — bukan tentang
         * jumlahnya. Disimpan sebagai satu angka, yang menjawab harus
         * menghitung ulang dengan aturan yang mungkin sudah berubah.
         */
        Schema::create('pay_slip', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('periode_id')->constrained('pay_periode')->cascadeOnDelete();
            $t->foreignId('pekerja_id')->constrained('mnr_pekerja')->cascadeOnDelete();

            /* ─── pendapatan ─── */
            $t->decimal('pokok', 14, 2)->default(0);
            $t->decimal('tunjangan_tetap', 14, 2)->default(0);
            $t->decimal('tunjangan_tidak_tetap', 14, 2)->default(0);

            /* Tunjangan site dihitung dari HARI ON-SITE YANG
               BENAR-BENAR TERCATAT, bukan dipukul rata sebulan. Itulah
               satu-satunya komponen yang tidak dapat dihitung HRIS mana
               pun yang tidak memegang absensinya sendiri. */
            $t->decimal('tunjangan_site', 14, 2)->default(0);
            $t->unsignedSmallInteger('hari_site')->default(0);

            $t->decimal('lembur', 14, 2)->default(0);
            $t->decimal('lembur_jam', 7, 2)->default(0);

            /* Iuran BPJS yang DITANGGUNG PERUSAHAAN ikut menambah
               penghasilan bruto pajak untuk JKK dan JKM — keduanya
               premi asuransi yang dibayar pemberi kerja bagi pekerja,
               dan karena itu objek PPh 21. */
            $t->decimal('bpjs_perusahaan', 14, 2)->default(0);

            $t->decimal('bruto', 14, 2)->default(0);

            /* ─── potongan ─── */
            $t->decimal('bpjs_karyawan', 14, 2)->default(0);
            $t->decimal('pph21', 14, 2)->default(0);
            $t->decimal('potongan_lain', 14, 2)->default(0);

            $t->decimal('neto', 14, 2)->default(0);

            /* ─── jejak perhitungan pajak ─── */
            $t->string('status_ptkp', 8)->nullable();
            $t->string('ter_kategori', 1)->nullable();
            $t->decimal('ter_tarif', 6, 4)->nullable();

            /* Rincian tiap komponen apa adanya — inilah yang menjawab
               "kenapa angkanya segini". */
            $t->json('rincian')->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['periode_id', 'pekerja_id']);
            $t->index(['company_id', 'periode_id']);
        });

        Schema::table('mnr_pekerja', function (Blueprint $t) {
            /* Status PTKP — TK/0 sampai K/3. Menentukan kategori TER
               dan besaran PTKP tahunan. Kosong, pajaknya tidak dapat
               dihitung sama sekali; diisi bawaan TK/0, seorang kepala
               keluarga beranak tiga dipotong pajak terlalu besar tiap
               bulan tanpa ada yang menandainya. */
            $t->string('status_ptkp', 8)->nullable()->after('tanggal_masuk');

            $t->string('npwp', 25)->nullable()->after('status_ptkp');
        });

        Schema::table('hr_upah', function (Blueprint $t) {
            /* Tunjangan site PER HARI, bukan per bulan. Pekerja FIFO
               berada di site empat belas hari dari dua puluh satu, dan
               tunjangan bulanan yang dipukul rata membayar tujuh hari
               yang ia habiskan di kampung halamannya. */
            $t->decimal('tunjangan_site_harian', 14, 2)->default(0)->after('tunjangan_tidak_tetap');
        });
    }

    public function down(): void
    {
        Schema::table('hr_upah', fn (Blueprint $t) => $t->dropColumn('tunjangan_site_harian'));
        Schema::table('mnr_pekerja', fn (Blueprint $t) => $t->dropColumn(['status_ptkp', 'npwp']));

        Schema::dropIfExists('pay_slip');
        Schema::dropIfExists('pay_periode');
        Schema::dropIfExists('pay_ter');
        Schema::dropIfExists('pay_acuan');
    }
};
