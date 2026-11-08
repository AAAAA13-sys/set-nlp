<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('auth_rate_limits')) {
            Schema::create('auth_rate_limits', function (Blueprint $table): void {
                $table->string('bucket_key', 96)->primary();
                $table->unsignedInteger('attempts')->default(0);
                $table->unsignedBigInteger('window_started_at');
                $table->unsignedBigInteger('blocked_until')->default(0);
                $table->string('updated_at', 32);
            });
        }

        if (!Schema::hasTable('security_audit_logs')) {
            Schema::create('security_audit_logs', function (Blueprint $table): void {
                $table->id();
                $table->string('event_type', 64)->index();
                $table->string('severity', 16)->default('INFO');
                $table->string('actor_role', 24)->nullable();
                $table->string('resource_path', 180)->nullable();
                $table->text('context_json')->nullable();
                $table->string('created_at', 32);
            });
        }

        if (!Schema::hasTable('webhook_replays')) {
            Schema::create('webhook_replays', function (Blueprint $table): void {
                $table->string('nonce_hash', 64)->primary();
                $table->unsignedBigInteger('received_at');
                $table->string('created_at', 32);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_replays');
        Schema::dropIfExists('security_audit_logs');
        Schema::dropIfExists('auth_rate_limits');
    }
};

