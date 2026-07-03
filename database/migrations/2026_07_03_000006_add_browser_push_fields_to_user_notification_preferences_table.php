<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table): void {
            $table->boolean('browser_push_enabled')->default(false)->after('digest_day_of_week');
            $table->boolean('critical_browser_push_enabled')->default(false)->after('browser_push_enabled');
            $table->boolean('maintenance_browser_push_enabled')->default(false)->after('critical_browser_push_enabled');
            $table->boolean('work_order_browser_push_enabled')->default(false)->after('maintenance_browser_push_enabled');
            $table->boolean('ai_recommendation_browser_push_enabled')->default(false)->after('work_order_browser_push_enabled');
            $table->boolean('lifecycle_browser_push_enabled')->default(false)->after('ai_recommendation_browser_push_enabled');
            $table->boolean('warranty_browser_push_enabled')->default(false)->after('lifecycle_browser_push_enabled');
            $table->boolean('evidence_browser_push_enabled')->default(false)->after('warranty_browser_push_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table): void {
            $table->dropColumn([
                'browser_push_enabled',
                'critical_browser_push_enabled',
                'maintenance_browser_push_enabled',
                'work_order_browser_push_enabled',
                'ai_recommendation_browser_push_enabled',
                'lifecycle_browser_push_enabled',
                'warranty_browser_push_enabled',
                'evidence_browser_push_enabled',
            ]);
        });
    }
};
