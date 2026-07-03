<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('system_notifications', function (Blueprint $table): void {
            $table->timestamp('email_sent_at')->nullable()->after('expires_at');
            $table->timestamp('email_failed_at')->nullable()->after('email_sent_at');
            $table->text('email_failure_reason')->nullable()->after('email_failed_at');
            $table->unsignedInteger('email_delivery_attempts')->default(0)->after('email_failure_reason');
        });
    }

    public function down(): void
    {
        Schema::table('system_notifications', function (Blueprint $table): void {
            $table->dropColumn([
                'email_sent_at',
                'email_failed_at',
                'email_failure_reason',
                'email_delivery_attempts',
            ]);
        });
    }
};
