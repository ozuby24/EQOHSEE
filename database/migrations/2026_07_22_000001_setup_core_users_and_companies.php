<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Inti bersama: registri perusahaan + peran & profil pada users.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->nullable();
            $t->string('parent')->nullable();
            $t->string('izin_type')->nullable();
            $t->string('commodity')->nullable();
            $t->string('location')->nullable();
            $t->string('address')->nullable();
            $t->string('ktt')->nullable();
            $t->string('pjo')->nullable();
            $t->integer('workers_employee')->default(0);
            $t->integer('workers_sub')->default(0);
            $t->string('risk_class')->default('Tinggi');
            $t->string('logo')->nullable();          // path file (Storage)
            $t->string('doc_no_prefix')->nullable();
            $t->timestamps();
        });

        // Menambah kolom ke tabel users bawaan Laravel
        // (menggantikan tabel 'profiles' + auth kustom lama)
        Schema::table('users', function (Blueprint $t) {
            $t->boolean('is_admin')->default(false);   // admin global (berlaku di semua modul)
            $t->string('lms_role')->nullable();        // trainee | trainer | ktt
            $t->string('audit_role')->nullable();      // auditor | company (modul audit)
            $t->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $t->string('avatar')->nullable();          // path file
            $t->string('employee_id')->nullable();
            $t->string('position')->nullable();
            $t->string('department')->nullable();
            $t->string('phone')->nullable();
            $t->boolean('active')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('company_id');
            $t->dropColumn([
                'is_admin', 'lms_role', 'audit_role', 'avatar',
                'employee_id', 'position', 'department', 'phone', 'active',
            ]);
        });
        Schema::dropIfExists('companies');
    }
};
