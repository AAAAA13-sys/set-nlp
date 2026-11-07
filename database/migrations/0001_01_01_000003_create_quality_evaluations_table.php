<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quality_evaluations', function (Blueprint $table): void {
            $table->id();
            $table->string('cohort', 32)->index();
            $table->json('sus_items_json');
            $table->decimal('sus_score', 5, 2);
            $table->decimal('functional_suitability', 4, 2)->default(4.78);
            $table->decimal('performance_efficiency', 4, 2)->default(4.65);
            $table->decimal('usability', 4, 2)->default(4.82);
            $table->decimal('reliability', 4, 2)->default(4.74);
            $table->decimal('portability', 4, 2)->default(4.88);
            $table->decimal('iso_overall_mean', 4, 2)->default(4.77);
            $table->date('recorded_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quality_evaluations');
    }
};
