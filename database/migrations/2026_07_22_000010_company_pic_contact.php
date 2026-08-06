<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kontak PIC tiap perusahaan — tujuan pengingat tindak lanjut temuan. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $t) {
            $t->string('pic_name')->nullable()->after('pjo');
            $t->string('pic_email')->nullable()->after('pic_name');
            $t->string('pic_phone')->nullable()->after('pic_email');   // format 62812xxxx untuk WhatsApp
        });
    }

    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->dropColumn(['pic_name','pic_email','pic_phone']));
    }
};
