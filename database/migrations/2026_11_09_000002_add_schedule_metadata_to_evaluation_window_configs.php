<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('evaluation_window_configs')) {
            Schema::table('evaluation_window_configs', function (Blueprint $table): void {
                if (!Schema::hasColumn('evaluation_window_configs', 'timezone')) {
                    $table->string('timezone', 48)->default('Asia/Manila')->after('ends_at');
                }
                if (!Schema::hasColumn('evaluation_window_configs', 'last_window_action')) {
                    $table->string('last_window_action', 32)->default('OPEN')->after('timezone');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('evaluation_window_configs')) {
            Schema::table('evaluation_window_configs', function (Blueprint $table): void {
                if (Schema::hasColumn('evaluation_window_configs', 'last_window_action')) {
                    $table->dropColumn('last_window_action');
                }
                if (Schema::hasColumn('evaluation_window_configs', 'timezone')) {
                    $table->dropColumn('timezone');
                }
            });
        }
    }
};

