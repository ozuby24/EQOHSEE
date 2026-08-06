<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Website #1 — LMS (Learning Center). Konversi dari cam_schema.sql (Supabase).
// Catatan: tabel 'profiles' lama dilebur ke 'users' (lihat migrasi inti).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('category')->nullable();
            $t->string('image')->nullable();            // dulu image_url -> path Storage
            $t->timestamps();
        });

        Schema::create('modules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->integer('order_index')->default(1);
            $t->timestamps();
        });

        Schema::create('materials', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('module_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->string('type')->nullable();             // pptx | video | pdf | document
            $t->string('url')->nullable();
            $t->longText('content')->nullable();
            $t->string('sop_url')->nullable();
            $t->integer('order_index')->default(1);
            $t->timestamps();
        });

        Schema::create('quizzes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('module_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->integer('pass_score')->default(70);
            $t->timestamps();
        });

        // Kuis pelatihan: correct_index BOLEH terbaca (dinilai di klien) — sesuai desain lama.
        Schema::create('quiz_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $t->text('question');
            $t->json('options');
            $t->integer('correct_index');
            $t->integer('order_index')->default(1);
            $t->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->constrained()->cascadeOnDelete();
            $t->integer('progress')->default(0);
            $t->string('status')->default('ongoing');   // ongoing | finished
            $t->timestamps();
            $t->unique(['user_id', 'course_id']);
        });

        Schema::create('module_completions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('module_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'module_id']);
        });

        Schema::create('quiz_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('quiz_id')->constrained()->cascadeOnDelete();
            $t->integer('score')->default(0);
            $t->boolean('passed')->default(false);
            $t->timestamps();
        });

        Schema::create('notes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('module_id')->constrained()->cascadeOnDelete();
            $t->text('content')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'module_id']);
        });

        Schema::create('procedures', function (Blueprint $t) {
            $t->id();
            $t->string('code')->nullable();
            $t->string('title');
            $t->string('category')->nullable();
            $t->text('description')->nullable();
            $t->string('url')->nullable();
            $t->integer('position')->default(1);
            $t->timestamps();
        });

        Schema::create('signatories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('title')->nullable();
            $t->string('signature')->nullable();        // dulu signature_url -> path Storage
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('certificates', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('certificate_number')->nullable();
            $t->string('recipient_name')->nullable();
            $t->string('course_title')->nullable();
            $t->integer('final_score')->nullable();
            $t->string('signed_by_name')->nullable();
            $t->foreignId('signatory_id')->nullable()->constrained('signatories')->nullOnDelete();
            $t->timestamp('issued_at')->useCurrent();
            $t->timestamps();
        });

        Schema::create('post_training_evaluations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('course_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('enrollment_id')->nullable()->constrained()->cascadeOnDelete();
            $t->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('trainer_name')->nullable();
            $t->integer('knowledge_score')->nullable();
            $t->integer('skill_score')->nullable();
            $t->integer('attitude_score')->nullable();
            $t->integer('safety_score')->nullable();
            $t->integer('overall_score')->nullable();
            $t->text('recommendation')->nullable();
            $t->text('strengths')->nullable();
            $t->text('improvements')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
        });

        Schema::create('news', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->longText('content')->nullable();
            $t->date('published_at')->nullable();
            $t->timestamps();
        });

        Schema::create('app_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->json('value')->nullable();
            $t->timestamps();
        });

        Schema::create('sop_evaluations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('procedure_id')->constrained()->cascadeOnDelete();
            $t->string('title');
            $t->text('description')->nullable();
            $t->integer('passing_score')->default(70);
            $t->integer('duration_minutes')->default(15);
            $t->boolean('is_active')->default(true);
            $t->integer('position')->default(1);
            $t->timestamps();
        });

        // Kunci jawaban Evaluasi SOP: correct_index TIDAK boleh dikirim ke klien.
        // Penilaian dilakukan di controller server-side (bukan di browser).
        Schema::create('sop_evaluation_questions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('evaluation_id')->constrained('sop_evaluations')->cascadeOnDelete();
            $t->text('question');
            $t->json('options');
            $t->integer('correct_index');
            $t->integer('order_index')->default(1);
            $t->timestamps();
        });

        Schema::create('sop_evaluation_attempts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('evaluation_id')->constrained('sop_evaluations')->cascadeOnDelete();
            $t->foreignId('procedure_id')->nullable()->constrained()->nullOnDelete();
            $t->integer('score');
            $t->integer('total');
            $t->integer('correct');
            $t->boolean('passed');
            $t->json('answers')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'sop_evaluation_attempts', 'sop_evaluation_questions', 'sop_evaluations',
            'app_settings', 'news', 'post_training_evaluations', 'certificates',
            'signatories', 'procedures', 'notes', 'quiz_attempts', 'module_completions',
            'enrollments', 'quiz_questions', 'quizzes', 'materials', 'modules', 'courses',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
