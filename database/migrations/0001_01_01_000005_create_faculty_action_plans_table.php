<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faculty_action_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('plan_id', 64)->unique();
            $table->foreignId('faculty_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->string('primary_cluster', 64)->default('Pedagogical Delivery');
            $table->string('status', 32)->default('PENDING_REVIEW');
            $table->string('urgency_level', 16)->default('MODERATE');
            $table->decimal('mean_score', 4, 2);
            $table->decimal('negative_sentiment_pct', 5, 2);
            $table->json('interventions_json')->nullable();
            $table->text('dean_notes')->nullable();
            $table->foreignId('signed_off_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_off_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faculty_action_plans');
    }
};
