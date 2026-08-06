<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $t) {
            $t->string('access_code', 20)->nullable()->after('category');   // kode dari trainer
            $t->boolean('require_code')->default(false)->after('access_code');
            $t->boolean('require_evaluation')->default(true)->after('auto_certificate');
        });

        // Kursus lama: beri kode acak agar siap dipakai bila fitur diaktifkan
        foreach (\App\Models\Course::whereNull('access_code')->get() as $c) {
            $c->update(['access_code' => \App\Models\Course::kodeBaru()]);
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $t) {
            $t->dropColumn(['access_code','require_code','require_evaluation']);
        });
    }
};
