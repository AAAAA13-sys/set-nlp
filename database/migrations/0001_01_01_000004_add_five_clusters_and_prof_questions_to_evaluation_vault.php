<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluation_vault_v2_extensions', function (Blueprint $table): void {
            $table->string('vault_id', 36)->primary();
            $table->json('cluster_scores_json')->nullable();
            $table->json('professor_questions_json')->nullable();
            $table->string('nlp_engine', 48)->default('calamanCy+VADER');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluation_vault_v2_extensions');
    }
};

