<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * TPKKP — bangun ulang mengikuti instrumen PTPKKP 2026.
 *
 * Kunci berubah: dulu satu baris per PERUSAHAAN, kini satu baris per PERIODE.
 * Perusahaan sekarang menjadi entitas kolom di dalam metode TD & FGD,
 * sehingga delapan perusahaan dinilai di dalam satu penilaian yang sama.
 *
 * Isi tabel lama dicadangkan ke storage/app/tpkkp-lama-<stamp>.json sebelum dibuang.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── cadangkan dulu ──
        if (Schema::hasTable('tpkkp_assessments')) {
            try {
                $lama = DB::table('tpkkp_assessments')->get();
                if ($lama->count()) {
                    $dir = storage_path('app');
                    if (!is_dir($dir)) mkdir($dir, 0775, true);
                    file_put_contents(
                        $dir . '/tpkkp-lama-' . date('Ymd-His') . '.json',
                        json_encode($lama, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    );
                }
            } catch (\Throwable $e) {
                // cadangan gagal bukan alasan menghentikan migrasi
            }
            Schema::drop('tpkkp_assessments');
        }

        Schema::create('tpkkp_assessments', function (Blueprint $t) {
            $t->id();
            $t->unsignedSmallInteger('tahun')->unique();
            $t->string('judul')->nullable();
            $t->string('status')->default('draft');   // draft | aktif | selesai

            $t->json('scores')->nullable();    // scores[metode][kode] = {v, e:{entitas:nilai}, ket}
            $t->json('roster')->nullable();    // entitas per metode
            $t->json('profil')->nullable();    // organisasi, site, komoditas, KTT, periode
            $t->json('tim')->nullable();       // [{nama, peran}]
            $t->json('programs')->nullable();  // program improvement
            $t->json('jadwal')->nullable();    // 21 kegiatan + tanda selesai
            $t->json('sampling')->nullable();  // populasi + rencana vs aktual

            $t->timestamps();
        });

        if (Schema::hasTable('tpkkp_responses') && !Schema::hasColumn('tpkkp_responses', 'assessment_id')) {
            Schema::table('tpkkp_responses', function (Blueprint $t) {
                $t->unsignedBigInteger('assessment_id')->nullable()->after('id');
                $t->index('assessment_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tpkkp_assessments');

        Schema::create('tpkkp_assessments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->unique()->constrained()->cascadeOnDelete();
            $t->json('scores')->nullable();
            $t->json('profil')->nullable();
            $t->json('strata')->nullable();
            $t->json('programs')->nullable();
            $t->timestamps();
        });

        if (Schema::hasTable('tpkkp_responses') && Schema::hasColumn('tpkkp_responses', 'assessment_id')) {
            Schema::table('tpkkp_responses', fn (Blueprint $t) => $t->dropColumn('assessment_id'));
        }
    }
};
