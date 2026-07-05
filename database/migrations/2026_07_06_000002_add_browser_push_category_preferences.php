<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table): void {
            $table->boolean('maintenance_request_browser_push_enabled')->default(false)->after('work_order_browser_push_enabled');
            $table->boolean('budget_browser_push_enabled')->default(false)->after('evidence_browser_push_enabled');
            $table->boolean('asset_action_browser_push_enabled')->default(false)->after('budget_browser_push_enabled');
            $table->boolean('executive_browser_push_enabled')->default(false)->after('asset_action_browser_push_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table): void {
            $table->dropColumn([
                'maintenance_request_browser_push_enabled',
                'budget_browser_push_enabled',
                'asset_action_browser_push_enabled',
                'executive_browser_push_enabled',
            ]);
        });
    }
};
