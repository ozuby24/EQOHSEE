<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pjp_laporans', function (Blueprint $table) {
            $table->string('kesesuaian_isi')->nullable()->after('catatan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pjp_laporans', function (Blueprint $table) {
            $table->dropColumn('kesesuaian_isi');
        });
    }
};
