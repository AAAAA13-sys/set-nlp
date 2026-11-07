<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_clearances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_section_id')->constrained('course_sections')->cascadeOnDelete();
            $table->foreignId('academic_term_id')->constrained('academic_terms')->cascadeOnDelete();
            $table->string('submission_key', 96)->unique();
            $table->string('status', 20)->default('PENDING')->index();
            $table->string('clearance_token', 128)->nullable()->unique();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'course_section_id'], 'uq_clearance_student_section');
            $table->index(['academic_term_id', 'status']);
        });

        Schema::create('evaluation_vault', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('course_section_id');
            $table->unsignedBigInteger('faculty_id');
            $table->unsignedBigInteger('academic_term_id');
            $table->json('ratings_json');
            $table->decimal('mean_score', 4, 2);
            $table->decimal('rating_variance', 6, 4);
            $table->boolean('is_straight_lined')->default(false);
            $table->unsignedInteger('dwell_time_seconds');
            $table->text('comment_text')->nullable();
            $table->string('comment_hash', 64)->index();
            $table->decimal('sentiment_polarity', 5, 4)->default(0.0000);
            $table->string('sentiment_valence', 16)->default('Neutral');
            $table->string('pedagogical_tag', 64)->default('Pedagogical Delivery');
            $table->date('recorded_date');

            $table->index(['course_section_id', 'faculty_id', 'academic_term_id'], 'idx_vault_section_faculty_term');
            $table->index(['faculty_id', 'sentiment_valence', 'pedagogical_tag'], 'idx_vault_faculty_sentiment_tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_vault');
        Schema::dropIfExists('evaluation_clearances');
    }
};
