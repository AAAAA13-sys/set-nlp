<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_window_configs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('academic_term_id')->unique()->constrained('academic_terms')->onDelete('cascade');
            $table->string('status', 24)->default('OPEN');
            $table->unsignedInteger('min_dwell_seconds')->default(45);
            $table->decimal('straight_line_variance_threshold', 5, 4)->default(0.0000);
            $table->decimal('watchlist_mean_threshold', 4, 2)->default(3.50);
            $table->decimal('watchlist_negative_sentiment_pct', 5, 2)->default(35.00);
            $table->text('announcement_message')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_window_configs');
    }
};
