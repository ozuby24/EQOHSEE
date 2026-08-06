<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Induk perusahaan sebagai relasi (IUJP mengikuti logo pemilik/IUP)
        Schema::table('companies', function (Blueprint $t) {
            $t->foreignId('parent_id')->nullable()->after('parent')
              ->constrained('companies')->nullOnDelete();
        });

        // Ragam desain sertifikat per kursus
        Schema::table('courses', function (Blueprint $t) {
            $t->string('cert_template')->default('klasik')->after('image');
            $t->boolean('auto_certificate')->default(true)->after('cert_template');
        });

        // Kode verifikasi + perusahaan penerbit pada sertifikat
        Schema::table('certificates', function (Blueprint $t) {
            $t->string('verification_code')->nullable()->unique()->after('certificate_number');
            $t->foreignId('company_id')->nullable()->after('course_id')
              ->constrained('companies')->nullOnDelete();
            $t->string('template')->default('klasik')->after('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('certificates', function (Blueprint $t) {
            $t->dropConstrainedForeignId('company_id');
            $t->dropColumn(['verification_code','template']);
        });
        Schema::table('courses', function (Blueprint $t) {
            $t->dropColumn(['cert_template','auto_certificate']);
        });
        Schema::table('companies', function (Blueprint $t) {
            $t->dropConstrainedForeignId('parent_id');
        });
    }
};
