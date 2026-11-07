<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_terms', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->string('academic_year', 24);
            $table->boolean('is_active')->default(false)->index();
            $table->boolean('evaluation_open')->default(true);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('courses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('code', 24)->unique();
            $table->string('title', 180);
            $table->unsignedTinyInteger('units')->default(3);
            $table->timestamps();
        });

        Schema::create('course_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('faculty_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->string('section_code', 32);
            $table->boolean('term_grades_submitted')->default(false);
            $table->boolean('registrar_lock')->default(false);
            $table->unsignedInteger('enrolled_count')->default(0);
            $table->timestamps();

            $table->unique(['course_id', 'academic_term_id', 'section_code'], 'uq_course_term_section');
            $table->index(['faculty_id', 'academic_term_id']);
        });

        Schema::create('enrollments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->string('enrollment_status', 24)->default('ENROLLED');
            $table->timestamps();

            $table->unique(['student_id', 'course_section_id'], 'uq_student_section_enrollment');
            $table->index(['student_id', 'academic_term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('course_sections');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('academic_terms');
    }
};
