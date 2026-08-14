<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu tabel tindak lanjut untuk seluruh modul.
 *
 * Konservasi memiliki konservasi_minerba_actions; bila Operasi dibuatkan
 * tabelnya sendiri dan delapan modul berikutnya mengikuti, yang lahir
 * adalah sepuluh skema yang mirip tetapi tidak sama, dan pertanyaan
 * "apa saja yang terlambat di seluruh site" berubah menjadi sepuluh
 * kueri yang harus disatukan dengan tangan.
 *
 * Baris konservasi dipindahkan, bukan ditinggalkan. Meninggalkannya
 * berarti dua tempat yang sama-sama berisi tindak lanjut, dan yang lama
 * akan tetap terbaca oleh kode yang belum sempat diperbarui.
 *
 * Status 'terlambat' dipetakan menjadi 'berjalan'. Keterlambatan bukan
 * keadaan yang ditetapkan seseorang melainkan akibat lewatnya tanggal,
 * dan menyimpannya membuatnya basi: baris bertanda "berjalan" yang
 * targetnya lewat sebulan lalu tidak pernah menjadi terlambat sampai ada
 * yang menyuntingnya. Model menghitungnya dari target_selesai, sehingga
 * tidak dapat tertinggal lagi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tindak_lanjut', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Baris asal yang melahirkannya — catatan operasi, catatan
            // konservasi, dan kelak temuan inspeksi — tanpa perlu menambah
            // kolom setiap kali ada modul baru.
            $t->nullableMorphs('sumber');

            // Modul pemiliknya, supaya daftar per modul tidak perlu
            // menyimpulkannya dari sumber_type yang boleh kosong.
            $t->string('modul')->index();

            // Kode peringatan yang melahirkannya; inilah yang menutup
            // lingkaran dari Peringatan ke Tindak lanjut.
            $t->string('kode_pemicu')->nullable();

            $t->string('judul');
            $t->string('kategori')->nullable();
            $t->string('prioritas')->default('sedang');
            $t->string('status')->default('rencana');
            $t->string('penanggung_jawab')->nullable();
            $t->date('target_selesai')->nullable();
            $t->date('selesai_pada')->nullable();
            $t->text('uraian')->nullable();
            $t->timestamps();

            // Daftar "yang terlambat" dibuka tiap hari dan disaring per
            // perusahaan; ketiga kolom inilah yang dipakai menyaringnya.
            $t->index(['modul', 'status', 'target_selesai']);
            $t->index(['company_id', 'status']);
        });

        if (Schema::hasTable('konservasi_minerba_actions')) {
            $this->pindahkanKonservasi();
            Schema::dropIfExists('konservasi_minerba_actions');
        }
    }

    public function down(): void
    {
        Schema::create('konservasi_minerba_actions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('record_id')->nullable()->constrained('konservasi_minerba_records')->nullOnDelete();
            $t->string('judul');
            $t->string('kategori')->default('recovery');
            $t->string('prioritas')->default('sedang');
            $t->string('status')->default('rencana');
            $t->string('penanggung_jawab')->nullable();
            $t->date('target_selesai')->nullable();
            $t->text('uraian')->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamps();

            $t->index(['status', 'prioritas']);
        });

        DB::table('tindak_lanjut')->where('modul', 'konservasi')->orderBy('id')
            ->chunk(200, function ($baris) {
                DB::table('konservasi_minerba_actions')->insert(
                    collect($baris)->map(fn ($r) => [
                        'company_id'       => $r->company_id,
                        'record_id'        => $r->sumber_id,
                        'judul'            => $r->judul,
                        'kategori'         => $r->kategori ?? 'recovery',
                        'prioritas'        => $r->prioritas,
                        'status'           => $r->status,
                        'penanggung_jawab' => $r->penanggung_jawab,
                        'target_selesai'   => $r->target_selesai,
                        'uraian'           => $r->uraian,
                        'user_id'          => $r->user_id,
                        'created_at'       => $r->created_at,
                        'updated_at'       => $r->updated_at,
                    ])->all()
                );
            });

        Schema::dropIfExists('tindak_lanjut');
    }

    private function pindahkanKonservasi(): void
    {
        DB::table('konservasi_minerba_actions')->orderBy('id')->chunk(200, function ($baris) {
            DB::table('tindak_lanjut')->insert(
                collect($baris)->map(fn ($r) => [
                    'company_id'       => $r->company_id,
                    'user_id'          => $r->user_id,
                    'sumber_type'      => $r->record_id ? \App\Models\MinerbaConservationRecord::class : null,
                    'sumber_id'        => $r->record_id,
                    'modul'            => 'konservasi',
                    'kode_pemicu'      => null,
                    'judul'            => $r->judul,
                    'kategori'         => $r->kategori,
                    'prioritas'        => $r->prioritas,
                    // 'terlambat' bukan keadaan, melainkan akibat lewatnya
                    // tanggal; yang tercatat begitu sesungguhnya berjalan.
                    'status'           => $r->status === 'terlambat' ? 'berjalan' : $r->status,
                    'penanggung_jawab' => $r->penanggung_jawab,
                    'target_selesai'   => $r->target_selesai,
                    'selesai_pada'     => $r->status === 'selesai' ? $r->updated_at : null,
                    'uraian'           => $r->uraian,
                    'created_at'       => $r->created_at,
                    'updated_at'       => $r->updated_at,
                ])->all()
            );
        });
    }
};
