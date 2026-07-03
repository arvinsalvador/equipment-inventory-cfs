<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table): void {
            $table->boolean('email_notifications_enabled')->default(false)->after('system_alerts');
            $table->boolean('immediate_critical_email_enabled')->default(false)->after('email_notifications_enabled');
            $table->boolean('daily_digest_email_enabled')->default(false)->after('immediate_critical_email_enabled');
            $table->boolean('weekly_digest_email_enabled')->default(false)->after('daily_digest_email_enabled');
            $table->string('digest_time')->nullable()->after('weekly_digest_email_enabled');
            $table->string('digest_day_of_week')->nullable()->after('digest_time');
        });
    }

    public function down(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table): void {
            $table->dropColumn([
                'email_notifications_enabled',
                'immediate_critical_email_enabled',
                'daily_digest_email_enabled',
                'weekly_digest_email_enabled',
                'digest_time',
                'digest_day_of_week',
            ]);
        });
    }
};
