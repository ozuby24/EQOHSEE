<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Izin kerja aman (permit to work).
 *
 * Alur yang persetujuannya mendahului pekerjaannya — persetujuan itu
 * ADALAH izinnya. Bedanya dari peledakan: izin kerja kedaluwarsa. Karena
 * itu waktu mulai dan selesai bukan keterangan melainkan bagian dari
 * izinnya, dan penutupan disimpan terpisah dari statusnya.
 *
 * Izin yang lewat waktu tetapi belum ditutup adalah kegagalan yang
 * paling sering terjadi dan paling jarang tercatat: pekerjaannya mungkin
 * sudah selesai, mungkin masih berjalan, dan tidak ada yang tahu yang
 * mana. Kolom `ditutup_pada` yang berdiri sendiri membuat keadaan itu
 * dapat ditanyakan lewat satu kueri, alih-alih disimpulkan dari status
 * yang sudah menyatakan hal lain.
 *
 * Syarat pemeriksaan berupa data, bukan daftar di dalam kode. Tiap
 * perusahaan memakai daftar periksanya sendiri, dan daftar itu berubah
 * setiap kali ada kejadian yang menambah satu baris ke dalamnya —
 * menanamkannya di kode berarti setiap perubahan prosedur menuntut
 * penerbitan aplikasi baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Daftar periksa per jenis izin.
        Schema::create('izin_syarats', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('jenis');
            $t->unsignedSmallInteger('urutan')->default(0);
            $t->text('teks');

            // Syarat wajib menghalangi penerbitan selama belum
            // terpenuhi; syarat tidak wajib hanya dicatat.
            $t->boolean('wajib')->default(true);
            $t->boolean('aktif')->default(true);
            $t->timestamps();

            $t->index(['company_id', 'jenis', 'aktif']);
        });

        // Ambang gas yang berlaku di situs ini.
        Schema::create('izin_ambangs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();

            $t->string('parameter');          // o2, lel, co, h2s
            $t->decimal('batas_min', 10, 3)->nullable();
            $t->decimal('batas_maks', 10, 3)->nullable();
            $t->string('satuan')->nullable();
            $t->string('acuan')->nullable();  // prosedur atau ketentuan yang mendasari
            $t->timestamps();

            $t->unique(['company_id', 'parameter']);
        });

        Schema::create('izin_kerjas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $t->string('nomor');
            $t->string('jenis');
            $t->string('lokasi');
            $t->text('uraian');
            $t->string('pelaksana')->nullable();          // regu atau kontraktor
            $t->unsignedSmallInteger('jumlah_pekerja')->nullable();
            $t->string('pengawas_lapangan')->nullable();

            // Masa berlaku. Bagian dari izinnya, bukan keterangan.
            $t->dateTime('mulai');
            $t->dateTime('selesai');

            // Batas umur uji gas khusus izin ini, bila situs memandang
            // pekerjaannya menuntut yang lebih pendek dari bawaan.
            $t->unsignedSmallInteger('batas_uji_menit')->nullable();

            $t->string('status')->default('draf');
            $t->foreignId('diajukan_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('diajukan_pada')->nullable();
            $t->foreignId('ditinjau_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('ditinjau_pada')->nullable();
            $t->text('alasan_tolak')->nullable();

            // Penutupan berdiri sendiri, di luar status alur: izin yang
            // sudah diterbitkan tetap berstatus disetujui setelah
            // ditutup, dan yang membedakan keduanya adalah kolom ini.
            $t->timestamp('ditutup_pada')->nullable();
            $t->foreignId('ditutup_oleh')->nullable()->constrained('users')->nullOnDelete();
            $t->text('catatan_penutupan')->nullable();

            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->unique(['company_id', 'nomor']);
            $t->index(['company_id', 'status']);
            $t->index(['mulai', 'selesai']);
            $t->index('lokasi');
        });

        // Jawaban daftar periksa pada satu izin.
        Schema::create('izin_periksas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('izin_kerja_id')->constrained()->cascadeOnDelete();
            $t->foreignId('izin_syarat_id')->nullable()->constrained()->nullOnDelete();

            // Teks syarat disalin ke sini. Daftar periksa berubah seiring
            // waktu, dan izin yang sudah diterbitkan harus tetap dapat
            // dibaca dengan syarat yang berlaku SAAT itu — bukan dengan
            // syarat yang berlaku saat berkasnya dibuka kembali.
            $t->text('teks');
            $t->boolean('wajib')->default(true);
            $t->boolean('terpenuhi')->default(false);
            $t->text('keterangan')->nullable();
            $t->timestamps();

            $t->index(['izin_kerja_id', 'terpenuhi']);
        });

        // Uji gas.
        Schema::create('izin_gas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('izin_kerja_id')->constrained()->cascadeOnDelete();

            $t->dateTime('waktu_uji');
            $t->decimal('o2', 8, 2)->nullable();
            $t->decimal('lel', 8, 2)->nullable();
            $t->decimal('co', 8, 2)->nullable();
            $t->decimal('h2s', 8, 2)->nullable();
            $t->string('alat')->nullable();
            $t->string('petugas')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamps();

            $t->index(['izin_kerja_id', 'waktu_uji']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('izin_gas');
        Schema::dropIfExists('izin_periksas');
        Schema::dropIfExists('izin_kerjas');
        Schema::dropIfExists('izin_ambangs');
        Schema::dropIfExists('izin_syarats');
    }
};
