<?php

use App\Support\Alur;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Memasang alur tinjauan pada data lapangan Operasi dan Konservasi.
 *
 * Sebelumnya kedua tabel hanya punya kolom `status` berupa teks bebas
 * yang diisi dari formulir, sehingga tidak ada catatan siapa yang
 * mengajukan, siapa yang meninjau, dan kapan. Kolom di sini yang
 * membuat pertanyaan itu bisa dijawab.
 *
 * Status lama dipetakan, bukan dibuang. Baris yang terlanjur bertanda
 * "disetujui" atau "terbit" tidak dipercaya begitu saja: tidak seorang
 * pun benar-benar meninjaunya — penandanya diisi sendiri oleh pengirim
 * data — sehingga semuanya turun menjadi draf. Menaikkannya menjadi
 * disetujui berarti membawa masuk angka yang belum pernah ditinjau ke
 * dalam laporan, dan sesudah itu tidak akan ada yang tahu mana yang
 * sudah benar-benar diperiksa.
 */
return new class extends Migration
{
    /**
     * Hanya tabel rekaman lapangan yang angkanya masuk KPI dan laporan.
     *
     * konservasi_minerba_actions sengaja tidak ikut. Kolom `status` di
     * sana menjawab pertanyaan lain — sampai mana tindak lanjutnya
     * dikerjakan (rencana, berjalan, selesai, terlambat) — dan memasang
     * alur persetujuan di atasnya akan menabrakkan dua arti pada satu
     * kolom yang sama.
     */
    private const TABEL = [
        'mine_operational_records',
        'konservasi_minerba_records',
    ];

    public function up(): void
    {
        foreach (self::TABEL as $tabel) {
            if (!Schema::hasTable($tabel)) continue;

            Schema::table($tabel, function (Blueprint $t) use ($tabel) {
                if (!Schema::hasColumn($tabel, 'diajukan_oleh')) {
                    $t->foreignId('diajukan_oleh')->nullable()->after('status')
                        ->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn($tabel, 'diajukan_pada')) {
                    $t->timestamp('diajukan_pada')->nullable()->after('diajukan_oleh');
                }
                if (!Schema::hasColumn($tabel, 'ditinjau_oleh')) {
                    $t->foreignId('ditinjau_oleh')->nullable()->after('diajukan_pada')
                        ->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn($tabel, 'ditinjau_pada')) {
                    $t->timestamp('ditinjau_pada')->nullable()->after('ditinjau_oleh');
                }
                if (!Schema::hasColumn($tabel, 'alasan_tolak')) {
                    $t->text('alasan_tolak')->nullable()->after('ditinjau_pada');
                }
            });

            // Daftar "menunggu tinjauan" dibuka tiap hari dan disaring
            // per perusahaan; tanpa indeks ini ia memindai seluruh tabel.
            Schema::table($tabel, function (Blueprint $t) use ($tabel) {
                $t->index(['status', 'company_id'], "{$tabel}_alur_idx");
            });

            DB::table($tabel)->update(['status' => Alur::DRAF]);
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $tabel) {
            if (!Schema::hasTable($tabel)) continue;

            Schema::table($tabel, function (Blueprint $t) use ($tabel) {
                $t->dropIndex("{$tabel}_alur_idx");
                $t->dropConstrainedForeignId('diajukan_oleh');
                $t->dropConstrainedForeignId('ditinjau_oleh');
                $t->dropColumn(['diajukan_pada', 'ditinjau_pada', 'alasan_tolak']);
            });

            DB::table($tabel)->update(['status' => 'draft']);
        }
    }
};
