<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Perbaikan: kolom `parent` semula NOT NULL dengan default 'PT CAM'.
 * Form yang dikirim kosong menjadi NULL secara eksplisit → melanggar NOT NULL.
 * Kolom dibuat nullable, dan default 'PT CAM' dihapus (branding EQOHSEE).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->string('parent')->nullable()->default(null)->change();
            $t->string('risk_class')->nullable()->default('Tinggi')->change();
            $t->integer('workers_employee')->nullable()->default(0)->change();
            $t->integer('workers_sub')->nullable()->default(0)->change();
        });

        // bersihkan sisa branding lama
        \Illuminate\Support\Facades\DB::table('companies')
            ->where('parent', 'PT CAM')->update(['parent' => null]);
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->string('parent')->nullable(false)->default('EQOHSEE')->change();
        });
    }
};
