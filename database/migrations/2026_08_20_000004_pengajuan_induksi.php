<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Surat pengajuan induksi — satu surat, banyak nama.
 *
 * INDUKSI TIDAK DIDAFTARKAN PER ORANG, dan bentuk sebelumnya
 * memaksakannya begitu: satu-satunya jalan mencatat induksi adalah
 * membuka berkas seorang pekerja lalu mengisi formulir di sana. Satu
 * kelas induksi berisi tiga puluh orang karena itu tercatat sebagai
 * tiga puluh baris yang tidak saling tahu — tidak ada tempat menyimpan
 * nomor registrasinya, tidak ada cara mengetahui siapa saja yang ikut
 * kelas yang sama, dan persetujuannya harus ditekan tiga puluh kali.
 *
 * Bentuknya sengaja KEMBAR dengan mcu_pengajuan. Keduanya benda yang
 * sama secara proses — surat berisi daftar nama yang melewati
 * persetujuan lalu dibalas hasilnya satu per satu — dan membuat
 * keduanya berbeda bentuk berarti tiap halaman, tiap rekap, dan tiap
 * penjagaan harus ditulis dua kali dengan dua cara.
 *
 * `paspor_induksi.induksi_pengajuan_id` boleh NULL: induksi susulan
 * untuk satu pekerja baru tidak lahir dari surat mana pun, dan
 * mewajibkannya akan membuat catatan itu tidak dapat dimasukkan sama
 * sekali.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('induksi_pengajuan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('nomor_register')->nullable();
            $t->date('tanggal');
            $t->string('judul')->nullable();
            $t->string('jenis')->default('Awal');   // Awal | Penyegaran | Perpanjangan | Tamu
            $t->string('lokasi')->nullable();       // ruang kelas / site
            $t->date('tgl_pelaksanaan')->nullable();
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

        Schema::table('paspor_induksi', function (Blueprint $t) {
            $t->foreignId('induksi_pengajuan_id')->nullable()->after('paspor_id')
              ->constrained('induksi_pengajuan')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('paspor_induksi', function (Blueprint $t) {
            $t->dropConstrainedForeignId('induksi_pengajuan_id');
        });

        Schema::dropIfExists('induksi_pengajuan');
    }
};
