<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

/**
 * Kategori perusahaan untuk modul audit SMKP (porting Aurorasys):
 * induk (pemegang IUP) | pjp (perusahaan jasa pertambangan) | sister |
 * lainnya.
 *
 * Kategori menentukan pengesah Rencana Audit (PJO wajib bagi PJP),
 * distribusi laporan, penanda tangan Berita Acara dan Laporan Audit, dan
 * urutan pilihan perusahaan. Batas data TIDAK bergantung padanya —
 * jangkauan tetap mengikuti `parent_id`.
 *
 * Perusahaan yang sudah ada diturunkan dari data yang ada: IUJP atau
 * perusahaan yang bernaung di bawah perusahaan lain → pjp, selain itu
 * induk. Sister company tidak dapat ditebak dan ditetapkan administrator.
 * Model Company mengisi kategori yang kosong dengan aturan yang sama
 * (Company::kategoriTurunan) untuk perusahaan yang dibuat sesudahnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('companies', 'kategori')) {
            Schema::table('companies', function (Blueprint $t) {
                $t->string('kategori', 20)->nullable()->after('code');
            });
        }

        DB::table('companies')->whereNull('kategori')->orderBy('id')
            ->get(['id', 'izin_type', 'parent_id'])
            ->each(function ($c) {
                $jasa = str_contains(strtoupper((string) $c->izin_type), 'IUJP') || $c->parent_id;
                DB::table('companies')->where('id', $c->id)->update(['kategori' => $jasa ? 'pjp' : 'induk']);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'kategori')) {
            Schema::table('companies', fn (Blueprint $t) => $t->dropColumn('kategori'));
        }
    }
};
