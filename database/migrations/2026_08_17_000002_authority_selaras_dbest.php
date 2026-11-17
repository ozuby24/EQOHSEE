<?php

use App\Support\Alur;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Authority disesuaikan dengan struktur D'Best yang sesungguhnya.
 *
 * Yang dibangun lebih dulu adalah PENYIMPAN CATATAN: MCU dan kartu
 * melekat langsung pada orangnya, tercatat begitu saja. Struktur
 * D'Best memperlihatkan bahwa ketiganya sebenarnya ALUR PENGAJUAN, dan
 * bentuk itu bukan kerumitan yang dapat dibuang:
 *
 * MCU DIAJUKAN PER ROMBONGAN, BUKAN PER ORANG. Perusahaan mengirim satu
 * surat berisi daftar pekerja ke klinik pemeriksa; nomor registernya,
 * tujuannya, dan persetujuannya melekat pada surat itu, bukan pada
 * masing-masing orang. Memodelkannya per orang membuat satu pengajuan
 * berisi empat puluh nama tercatat sebagai empat puluh pengajuan yang
 * tidak saling tahu — dan persetujuannya harus ditekan empat puluh
 * kali.
 *
 * SIMPER PUNYA BERKAS SYARAT. Yang menentukan seseorang boleh
 * mengemudi di area tambang bukan kartunya melainkan yang mendahului
 * kartu itu: SIM kepolisian yang masih berlaku, bukti induksi, dan
 * sertifikat mengemudi defensif. Tanpa medan itu, kartu yang terbit
 * tidak dapat ditelusuri dasarnya.
 *
 * INDUKSI BELUM ADA SAMA SEKALI, padahal ia syarat pertama sebelum
 * seseorang boleh memasuki area tambang — mendahului MCU maupun kartu.
 *
 * Persetujuannya memakai App\Support\Alur yang sudah dipakai seluruh
 * modul lain (draf → diajukan → disetujui/ditolak, peninjau bukan
 * pengaju), bukan alur khusus. Satu orang tidak perlu belajar dua alur
 * persetujuan yang berbeda hanya karena modulnya berbeda.
 */
return new class extends Migration
{
    public function up(): void
    {
        /* ── pengajuan MCU: satu surat, banyak nama ── */
        Schema::create('mcu_pengajuan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('nomor_register')->nullable();
            $t->date('tanggal');
            $t->string('kepada')->nullable();        // klinik / rumah sakit yang dituju
            $t->string('judul')->nullable();
            $t->string('jenis')->default('Berkala'); // Awal | Berkala | Khusus | Purna
            $t->text('catatan')->nullable();

            /* Kolom alur persetujuan, sama bentuknya dengan modul lain. */
            $t->string('status')->default('draf');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            $t->timestamps();

            $t->index(['company_id', 'status']);
        });

        /* ── hasil MCU per orang: medan yang belum ada ── */
        Schema::table('paspor_mcu', function (Blueprint $t) {
            /* Ditautkan ke pengajuannya BILA lahir dari sana. MCU yang
               dicatat susulan — pekerja baru, pemeriksaan khusus — tidak
               punya surat pengajuan, dan memaksakannya akan membuat
               catatan itu tidak dapat dimasukkan sama sekali. */
            $t->foreignId('mcu_pengajuan_id')->nullable()->after('paspor_id')
              ->constrained('mcu_pengajuan')->nullOnDelete();

            $t->string('nomor')->nullable()->after('penyelenggara');   // no. hasil MCU

            /* HASIL BOLEH KOSONG SEKARANG, dan itu perbaikan pokok.
               Sebelumnya kolomnya NOT NULL dengan nilai awal 'Fit' —
               masuk akal selama MCU hanya dicatat SESUDAH diperiksa.
               Begitu pengajuan rombongan ada, barisnya lahir saat surat
               dikirim dan hasilnya baru kembali berminggu-minggu
               kemudian; dengan nilai awal itu, seluruh nama yang belum
               diperiksa akan terbaca "Fit" — dinyatakan sehat oleh
               sistem tanpa seorang dokter pun melihatnya. */
            $t->string('hasil')->nullable()->default(null)->change();

            /* Rujukan medis dan tanggal tindak lanjutnya. Hasil "Fit
               With Note" yang tidak punya tanggal tindak lanjut adalah
               catatan yang tidak pernah ditagih siapa pun. */
            $t->string('rujukan')->nullable()->after('pembatasan');
            $t->date('outstanding')->nullable()->after('rujukan');
        });

        /* ── kartu: berkas syarat yang mendahului penerbitannya ── */
        Schema::table('paspor_kartu', function (Blueprint $t) {
            $t->string('sim_polisi')->nullable()->after('golongan');       // nomor SIM kepolisian
            $t->date('sim_polisi_expired')->nullable()->after('sim_polisi');
            $t->string('pengalaman_kerja')->nullable()->after('sim_polisi_expired');

            /* Berkas syarat. Disimpan sebagai jalur, bukan diunggah
               ulang tiap perpanjangan. */
            $t->string('berkas_induksi')->nullable()->after('pengalaman_kerja');
            $t->string('berkas_ddt')->nullable()->after('berkas_induksi');  // defensive driving

            $t->string('email_atasan')->nullable()->after('berkas_ddt');

            /* Terbit | Perpanjangan | Peningkatan golongan. Tiga hal
               yang di D'Best punya formulirnya sendiri-sendiri; di sini
               satu tabel dengan penanda, sebab datanya sama dan yang
               berbeda hanya sebabnya. */
            $t->string('sebab_terbit')->default('Terbit')->after('jenis');

            $t->string('status')->default('draf')->after('sebab_terbit');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();
        });

        /* Kartu yang SUDAH ADA sebelum alur ini dipasang dianggap sudah
           disetujui.

           Tanpa baris ini, seluruh kartu yang selama ini tercatat
           mendadak berstatus draf — dan karena kartu draf tidak
           meloloskan siapa pun di gerbang, setiap pekerja yang datanya
           sudah lengkap berubah menjadi "tidak layak" pada pagi setelah
           pemutakhiran. Bukan temuan keselamatan, melainkan cacat
           pemutakhiran yang menyamar sebagai temuan — dan yang paling
           mungkin terjadi berikutnya adalah penandanya diabaikan
           seluruhnya.

           Yang tercatat memang sudah terbit: di model lama, mencatat
           kartu berarti kartunya ada di tangan orangnya. */
        DB::table('paspor_kartu')->update(['status' => Alur::DISETUJUI]);

        /* ── induksi keselamatan ── */
        Schema::create('paspor_induksi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('paspor_id')->constrained('paspor')->cascadeOnDelete();

            $t->string('nomor_registrasi')->nullable();
            $t->string('jenis')->default('Awal');   // Awal | Penyegaran | Perpanjangan | Tamu
            $t->date('tanggal');
            $t->date('tgl_expired')->nullable();

            $t->string('pemberi')->nullable();      // yang memberikan induksi
            $t->string('lokasi')->nullable();
            $t->unsignedSmallInteger('nilai')->nullable();
            $t->string('hasil')->default('Lulus');  // Lulus | Tidak Lulus | Mengulang

            $t->string('berkas')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['paspor_id', 'tgl_expired']);
            $t->index('tgl_expired');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paspor_induksi');

        Schema::table('paspor_kartu', function (Blueprint $t) {
            $t->dropConstrainedForeignId('diajukan_oleh');
            $t->dropConstrainedForeignId('ditinjau_oleh');
            $t->dropColumn([
                'sim_polisi', 'sim_polisi_expired', 'pengalaman_kerja',
                'berkas_induksi', 'berkas_ddt', 'email_atasan',
                'sebab_terbit', 'status', 'diajukan_pada', 'ditinjau_pada', 'alasan_tolak',
            ]);
        });

        Schema::table('paspor_mcu', function (Blueprint $t) {
            $t->dropConstrainedForeignId('mcu_pengajuan_id');
            $t->dropColumn(['nomor', 'rujukan', 'outstanding']);
        });

        /* Dikembalikan NOT NULL, jadi baris yang hasilnya belum kembali
           harus punya isi lebih dulu — kalau tidak, pembalikannya gagal
           di tengah jalan dan meninggalkan skema separuh jadi. */
        DB::table('paspor_mcu')->whereNull('hasil')->update(['hasil' => 'Fit']);

        Schema::table('paspor_mcu', function (Blueprint $t) {
            $t->string('hasil')->default('Fit')->nullable(false)->change();
        });

        Schema::dropIfExists('mcu_pengajuan');
    }
};
